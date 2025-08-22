<?php declare(strict_types=1);

namespace Deduplicate\Controller;

use Common\Stdlib\PsrMessage;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    public function indexAction()
    {
        $params = $this->params()->fromRoute();
        $params['action'] = 'auto';
        return $this->forward()->dispatch(__CLASS__, $params);
    }

    public function autoAction()
    {
        /** @var \Deduplicate\Form\DeduplicateAutoForm $form */
        $form = $this->getForm(\Deduplicate\Form\DeduplicateAutoForm::class);

        $request = $this->getRequest();

        $resourceType = 'items';
        $method = 'equal';
        $query = [];
        $resourceIds = [];
        $batch = null;

        $view = new ViewModel([
            'form' => $form,
            'resourceType' => $resourceType,
            'property' => null,
            'query' => $query,
            'method' => $method,
            'duplicates' => [],
            'skips' => [],
            'process' => '0',
        ]);

        $hiddenProcess = [
            'name' => 'process',
            'type' => \Laminas\Form\Element\Hidden::class,
            'attributes' => [
                'id' => 'deduplicate-process',
                'value' => '0',
            ],
        ];

        if (!$request->isPost()) {
            $form->remove('process')->add($hiddenProcess);
            return $view;
        }

        $params = $request->getPost();

        if (!empty($params['batch_action'])) {
            $batch = strpos($params['batch_action'], 'selected') === false ? 'all' : 'selected';
            if ($batch === 'all') {
                $query = $params['query'] ?? '[]';
                $query = array_diff_key(json_decode($query, true) ?: [], array_flip(['csrf', 'sort_by', 'sort_order', 'sort_by_default', 'sort_order_default', 'page', 'per_page', 'offset', 'limit']));
            } else {
                $resourceIds = $params['resource_ids'] ?? [];
                $query = ['id' => array_unique(array_filter(array_map('intval', $resourceIds)))];
            }
            $resourceType = $params['resource_type'] ?? $resourceType;
            $resourceType = $this->easyMeta()->resourceName($resourceType);
            $form
                ->remove('process')
                ->add($hiddenProcess)
                ->add([
                    'name' => 'resource_type',
                    'type' => \Laminas\Form\Element\Hidden::class,
                    'attributes' => [
                        'id' => 'deduplicate-resource-type',
                        'value' => $resourceType,
                    ],
                ])
                ->add([
                    'name' => 'query',
                    'type' => \Laminas\Form\Element\Hidden::class,
                    'attributes' => [
                        'id' => 'deduplicate-query',
                        'value' => json_encode($query, 320),
                    ],
                ]);
            return$view
                ->setVariable('resource_type', $resourceType)
                ->setVariable('query', $query);
        }

        $form->setData($params);
        if (!$form->isValid()) {
            $this->messenger()->addErrors($form->getMessages());
            return $view;
        }

        $data = $form->getData();
        $property = $data['deduplicate_property'] ?? null;
        if (!$property) {
            $this->messenger()->addError(
                'A property to deduplicate on is required.' // @translate
            );
            return $view;
        }

        unset($data['csrf']);

        if (empty($data['query']) || in_array($data['query'], ['[]', '{}'])) {
            $query = [];
        } elseif (mb_substr($data['query'], 0, 1) === '{') {
            $query = json_decode($data['query'], true);
        } else {
            parse_str($data['query'], $query);
        }

        $resourceType = $data['resource_type'] ?? $resourceType;
        $resourceType = $this->easyMeta()->resourceName($resourceType);

        $duplicates = $this->getDuplicates($resourceType, $property, $method, $query);

        $view = new ViewModel([
            'form' => $form,
            'resourceType' => $resourceType,
            'property' => $property,
            'query' => $query,
            'method' => $method,
            'duplicates' => $duplicates,
            'skips' => [],
            'process' => (int) !empty($data['process']),
        ]);

        if (!count($duplicates)) {
            $this->messenger()->addSuccess(new PsrMessage(
                'There are no duplicates for the property {property}.', // @translate
                ['property' => $property]
            ));
            $form->remove('process')->add($hiddenProcess);
            return $view;
        }

        $this->messenger()->addSuccess(new PsrMessage(
            'There are {count} duplicates for the property {property}.', // @translate
            ['count' => count($duplicates), 'property' => $property]
        ));

        // Check for duplicated resources with duplicated values.
        $skips = [];
        $rss = array_values($duplicates);
        foreach ($rss as $k => $rs) {
            foreach ($rs as $r) {
                foreach ($rss as $kk => $rs2) {
                    if ($kk > $k && in_array($r, $rs2)) {
                        $skips[$r] = $r;
                        break;
                    }
                }
            }
        }
        $skips = array_values($skips);

        $view->setVariable('skips', $skips);

        if ($skips) {
            $this->messenger()->addWarning(new PsrMessage(
                '{count} resources are duplicated with several values and are kept: {resource_ids}', // @translate
                ['count' => count($skips), 'resource_ids' => implode(', ', $skips)]
            ));
        }

        // Add a security: don't change property.
        $selectedProperty = [
            'name' => 'property_selected',
            'type' => \Laminas\Form\Element\Hidden::class,
            'attributes' => [
                'value' => $property,
            ],
        ];

        $form->get('deduplicate_property')->setAttribute('readonly', 'readonly');
        $form->add($selectedProperty);

        if (empty($data['process'])) {
            $this->messenger()->addWarning(
                'Confirm removing duplicates, except the first, by checking the checkbox.' // @translate
            );
            return $view;
        }

        if (($params['property_selected'] ?? null) !== $property) {
            $this->messenger()->addWarning(
                'You cannot change property when submitting form.' // @translate
            );
            return $view;
        }

        // Prepare reload.
        $form->get('process')->setValue('0');

        // Remove first resource and keep only the list of resources.
        // The results are already ordered by resource id.
        $result = [];
        foreach ($duplicates as $rs) {
            array_shift($rs);
            $result[] = $rs;
        }

        $result = array_unique(array_merge(...$result));
        $result = array_diff($result, $skips);
        sort($result);

        if (!count($result)) {
            $this->messenger()->addWarning(
                'No duplicates were removed, because duplicated resources have multiple duplicate values.' // @translate
            );
        } elseif (count($result) <= 100) {
            try {
                $this->api()->batchDelete($resourceType, $result);
            } catch (\Exception $e) {
                $this->messenger()->addWarning(new PsrMessage(
                    'An error occurred when deleting duplicates: {msg}.', // @translate
                    ['msg' => $e->getMessage()]
                ));
                return $view;
            }
        } else {
            // Use a job for big deduplication.
            // Use the Omeka job.
            $jobParams = [
                'resource' => $resourceType,
                'query' => ['id' => $result],
            ];
            $job = $this->jobDispatcher()->dispatch(\Omeka\Job\BatchDelete::class, $jobParams);
            $urlPlugin = $this->url();
            // This job has no log.
            $message = new PsrMessage(
                'Processing deduplication in background (job {link}#{job_id}{link_end}, {link_log}logs{link_end}).', // @translate
                [
                    'link' => sprintf(
                        '<a href="%s">',
                        htmlspecialchars($urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]))
                    ),
                    'job_id' => $job->getId(),
                    'link_end' => '</a>',
                    'link_log' => sprintf(
                        '<a href="%1$s">',
                        class_exists('Log\Module', false)
                            ? $urlPlugin->fromRoute('admin/default', ['controller' => 'log'], ['query' => ['job_id' => $job->getId()]])
                            : $urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'action' => 'log', 'id' => $job->getId()])
                    ),
                ]
            );
            $message->setEscapeHtml(false);
            $this->messenger()->addSuccess($message);
        }

        $this->messenger()->addWarning(new PsrMessage(
            '{count} duplicates were removed.', // @translate
            ['count' => count($result)]
        ));

        return $view;
    }

    public function manualAction()
    {
        /** @var \Deduplicate\Form\DeduplicateForm $form */
        $form = $this->getForm(\Deduplicate\Form\DeduplicateForm::class);

        $request = $this->getRequest();
        $params = $request->getPost();

        $args = [
            'resources' => [],
            'form' => $form,
            'resourceType' => 'items',
            'query' => [],
            'property' => null,
            'value' => '',
            'method' => null,
            'totalResourcesQuery' => null,
            'totalResources' => null,
        ];

        // TODO The check may be done in the form.

        $api = $this->api();
        $hasError = false;
        $isPost = $request->isPost();

        if ($isPost) {
            $property = empty($params['deduplicate_property']) ? null : $params['deduplicate_property'];
            $propertyTerm = $this->easyMeta()->propertyTerm($property);
            if ($property && !$propertyTerm) {
                $this->messenger()->addError(new PsrMessage(
                    'The property {property} does not exist.', // @translate
                    ['property' => $params['deduplicate_property']]
                ));
                $hasError = true;
            } else {
                $property = $propertyTerm;
            }

            $value = $params['deduplicate_value'] ?? '';
            if ($property && !strlen($value)) {
                $this->messenger()->addError(
                    'A value to deduplicate on is required.' // @translate
                );
                $hasError = true;
            } elseif (!$property && strlen($value)) {
                $this->messenger()->addError(
                    'A property is required to search on.' // @translate
                );
                $hasError = true;
            }

            if (mb_strlen($value) > 255) {
                $this->messenger()->addError(
                    'The string is too long (more than {length} characters).', // @translate
                    ['length' => 255]
                );
                $hasError = true;
            }

            $args['value'] = $value;

            $resourceTypes = [
                'item' => 'items',
                'item-set' => 'item_sets',
                'media' => 'media',
                'items' => 'items',
                'item_sets' => 'item_sets',
            ];

            $resourceType = empty($params['resource_type']) || !isset($resourceTypes[$params['resource_type']])
                ? 'items'
                : $resourceTypes[$params['resource_type']];
            $args['resourceType'] = $resourceType;

            $query = [];

            $batchAction = isset($params['deduplicate_selected']) || (isset($params['batch_action']) && $params['batch_action'] === 'deduplicate_selected')
                ? 'selected'
                : 'all';

            if ($batchAction === 'selected') {
                $resourceIds = $params['resource_ids'] ?? [];
                $resourceIds = array_unique(array_filter(array_map('intval', $resourceIds)));
                if (!$resourceIds) {
                    $this->messenger()->addError(
                        'The query does not find selected resource ids.' // @translate
                    );
                    $hasError = true;
                }
                $query = ['id' => $resourceIds];
            } else {
                $query = $params['query'] ?? '[]';
                $query = array_diff_key(json_decode($query, true) ?: [], array_flip(['csrf', 'sort_by', 'sort_order', 'sort_by_default', 'sort_order_default', 'page', 'per_page', 'offset', 'limit']));
            }

            if ($query) {
                $args['query'] = $query;
                $args['totalResourcesQuery'] = $api->search($resourceType, $query + ['limit' => 0])->getTotalResults();
                if (!$args['totalResourcesQuery']) {
                    $this->messenger()->addError(
                        'The query returned no resource.' // @translate
                    );
                    $hasError = true;
                }
            }

            $method = $params['method'] ?? 'equal';
            $args['method'] = $method;

            if (!$hasError) {
                // Do the search via module AdvancedSearch.
                $queryResources = $query;
                if ($property && strlen($value)) {
                    $nearValues = $this->getValuesNear($method, $value, $property, $resourceType, $query);
                    if (is_null($nearValues)) {
                        $this->messenger()->addWarning(new PsrMessage(
                            'There are too many similar values near "{value}". You may filter resources first.', // @translate
                            ['value' => $value]
                        ));
                        $hasError = true;
                    } elseif (!$nearValues) {
                        $this->messenger()->addWarning(new PsrMessage(
                            'There is no existing value for property {property} near "{value}".', // @translate
                            ['property' => $property, 'value' => $value]
                        ));
                        $hasError = true;
                    } else {
                        /* TODO Add a near query in Advanced Search via mysql.
                        $queryResources['property'][] = [
                            'property' => $property,
                            'type' => 'near',
                            'value' => $value,
                        ];
                        */
                        $queryResources['property'][] = [
                            'property' => $property,
                            'type' => 'list',
                            'text' => $nearValues,
                        ];
                    }
                }
                if (!$hasError) {
                    $queryResources['limit'] = $this->settings()->get('pagination_per_page') ?: \Omeka\Stdlib\Paginator::PER_PAGE;

                    $response = $api->search($resourceType, $queryResources);
                    $args['resources'] = $response->getContent();
                    $args['totalResources'] = $response->getTotalResults();
                }
            }

            $resourceId = isset($params['resource_id']) ? (int) $params['resource_id'] : 0;
            $resourcesMerged = [];
            if ($resourceId) {
                $resource = $api->search($resourceType, ['id' => $resourceId])->getContent();
                if (!$resource) {
                    $this->messenger()->addError(new PsrMessage(
                        'The resource #{resource_id} does not exist.', // @translate
                        ['resource_id' => $params['resource_id']]
                    ));
                    $hasError = true;
                }

                // Sometime, a 0 is included in the list of selected resource
                // ids and that may break advanced search.

                if (!empty($params['resources_merged'])) {
                    $params['resources_merged'] = array_unique(array_filter($params['resources_merged'])) ?: [];
                    $resourcesMerged = $api->search($resourceType, ['id' => $params['resources_merged']], ['returnScalar' => 'id'])->getContent();
                    if (!$resourcesMerged || count($resourcesMerged) !== count($params['resources_merged'])) {
                        $this->messenger()->addError(new PsrMessage(
                            'Some merged resources do not exist.' // @translate
                        ));
                        $hasError = true;
                    }
                }
            }
        }

        $form->init();
        $form->setData([
            'deduplicate_property' => $property ?? null,
            'deduplicate_value' => $value ?? null,
            'method' => $method ?? 'equal',
            'csrf' => $params['csrf'] ?? null,
        ]);

        $view = new ViewModel($args);

        if ($hasError || !$isPost || isset($params['batch_action'])) {
            return $view;
        }

        // Useless: the form is checked above.
        if (!$form->isValid()) {
            $this->messenger()->addErrors($form->getMessages());
            return $view;
        }

        // The process may be heavy with many linked resources, so use a job.
        if ($resourceId && $resourcesMerged) {
            $jobParams = [
                'resourceId' => $resourceId,
                'resourcesMerged' => array_values($resourcesMerged),
            ];
            $job = $this->jobDispatcher()->dispatch(\Deduplicate\Job\DeduplicateResources::class, $jobParams);
            $urlPlugin = $this->url();
            $message = new PsrMessage(
                'Processing deduplication in background (job {link}#{job_id}{link_end}, {link_log}logs{link_end}).', // @translate
                [
                    'link' => sprintf(
                        '<a href="%s">',
                        htmlspecialchars($urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]))
                    ),
                    'job_id' => $job->getId(),
                    'link_end' => '</a>',
                    'link_log' => sprintf(
                        '<a href="%1$s">',
                        class_exists('Log\Module', false)
                            ? $urlPlugin->fromRoute('admin/default', ['controller' => 'log'], ['query' => ['job_id' => $job->getId()]])
                            : $urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'action' => 'log', 'id' => $job->getId()])
                    ),
                ]
            );
            $message->setEscapeHtml(false);
            $this->messenger()->addSuccess($message);
            return $view;
        }

        return $view;
    }

    /**
     * Search all resources grouped by duplicate values on the specified property.
     */
    protected function getDuplicates(string $resourceType, string $property, string $method, array $query): array
    {
        $propertyId = $this->easyMeta()->propertyId($property);
        if (!$propertyId) {
            return [];
        }

        $methods = [
            'equal',
            'equal_insensitive',
        ];
        $method = in_array($method, $methods) ? $method : 'equal';

        $filteredIds = null;
        if ($query) {
            $query['property'][] = [
                'property' => $propertyId,
                'type' => 'ex',
            ];
            $response = $this->api()->search($resourceType, $query, ['returnScalar' => 'id']);
            if (!$response->getTotalResults()) {
                return [];
            }
            $filteredIds = array_map('intval', $response->getContent());
        }

        // Get all values for the specified resources.

        $resourceTypesToTable = [
            'items' => 'item',
            'item-set' => 'item_set',
            'item_sets' => 'item_set',
            'media' => 'media',
        ];
        $table = $resourceTypesToTable[$resourceType] ?? $resourceType;

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->api()->read('vocabularies', 1)->getContent()->getServiceLocator()->get('Omeka\Connection');

        // The previous version used a similar query, but with php processing,
        // and the query was not optimized and slower.

        // A transactional is required because group_concat is used and its
        // limit should be larger.
        // The setting "group_concat_max_len" should be lower than "max_allowed_packet",
        // divided by the number of rows. The default is 1024 in mysql and 1MB
        // in recent version of mariadb. 1000000 characters mean that until
        // 200000 resources can be duplicated with the same value, so it is
        // largely enough in real cases and the process can be redone, because
        // only the first is kept.
        // Warning: the last number may be cut.
        $duplicates = $connection->transactional(function(\Doctrine\DBAL\Connection $connection)
            use ($table, $propertyId, $method, $filteredIds): array
        {
            $bind = ['property_id' => $propertyId];
            $types = ['property_id' => \Doctrine\DBAL\ParameterType::INTEGER];

            if ($method === 'equal_insensitive') {
                $modeStart = 'LOWER(';
                $modeEnd = ')';
            } else {
                $modeStart = 'CAST(';
                $modeEnd = ' AS BINARY)';
            }

            if ($filteredIds) {
                $bind['resource_ids'] = $filteredIds;
                $types['resource_ids'] = $connection::PARAM_INT_ARRAY;
                $andFilteredIds = 'AND (`value`.`resource_id` IN (:resource_ids))';
            } else {
                $andFilteredIds = '';
            }

            // Another way to do it is to check the count and to do a second
            // query when the group is lower than the number.
            $connection->executeQuery('SET SESSION group_concat_max_len = 1000000;');
            $sql = <<<SQL
                SELECT
                    $modeStart`value`.`value`$modeEnd AS v,
                    GROUP_CONCAT(DISTINCT `value`.`resource_id` ORDER BY `value`.`resource_id` ASC) AS i
                FROM `value` `value`
                INNER JOIN `$table` `resource` ON `resource`.`id` = `value`.`resource_id`
                WHERE (`value`.`property_id` = :property_id)
                    AND (`value`.`value` <> "")
                    AND (`value`.`value` IS NOT NULL)
                    AND (`value`.`value_resource_id` IS NULL)
                    $andFilteredIds
                GROUP BY $modeStart`value`.`value`$modeEnd
                HAVING COUNT(DISTINCT `value`.`resource_id`) > 1
                ORDER BY $modeStart`value`.`value`$modeEnd ASC;
                SQL;

            $result = $connection->executeQuery($sql, $bind, $types);
            return $result->fetchAllAssociative();
        });

        $result = [];
        foreach ($duplicates as $data) {
            $result[$data['v']] = explode(',', $data['i']);
            if (strlen($data['i']) > 1000000 - 10) {
                $this->messenger()->addWarning(new PsrMessage(
                    'The value {value} has too much duplicates and will requires a second deduplication.', // @translate
                    ['value' => $data['v']]
                ));
                // Cut the last resource id to avoid a wrong id.
                array_pop($result[$data['v']]);
            }
        }

        return $result;
    }

    /**
     * There is no simple way to do a search "similar" in mysql and doctrine
     * doesn't implement "soundex" easily, so process via php.
     */
    protected function getValuesNear(string $method, ?string $value, ?string $property, string $resourceType, array $query): array
    {
        if (is_null($value) || !strlen($value)) {
            return [];
        }

        $propertyId = $this->easyMeta()->propertyId($property);
        if (!$propertyId) {
            return [];
        }

        $methods = [
            'equal',
            'similar_text',
            'levenshtein',
            'metaphone',
            'soundex',
        ];
        $method = in_array($method, $methods) ? $method : 'equal';
        if ($method === 'equal') {
            return [$value];
        }

        $query['property'][] = [
            'property' => $propertyId,
            'type' => 'ex',
        ];
        $response = $this->api()->search($resourceType, $query, ['returnScalar' => 'id']);
        if (!$response->getTotalResults()) {
            return [];
        }
        $filteredIds = $response->getContent();

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->api()->read('vocabularies', 1)->getContent()->getServiceLocator()->get('Omeka\Connection');
        $qb = $connection->createQueryBuilder();
        $expr = $qb->expr();
        $qb
            ->distinct()
            ->select('value.value')
            ->from('value', 'value')
            ->where($expr->eq('value.property_id', ':property_id'))
            ->andWhere($expr->in('value.resource_id', ':resource_ids'))
            ->andWhere($expr->neq('value.value', ''))
            ->andWhere($expr->isNotNull('value.value'))
            // TODO What is the purpuse of this limit (for big values)? Once the value is specified, there is no issue.
            // ->andWhere($expr->lte('LENGTH(value.value)', 255))
            ->addOrderBy('value.value', 'asc')
        ;
        $bind = [
            'property_id' => $propertyId,
            'resource_ids' => $filteredIds,
        ];
        $types = [
            'property_id' => \Doctrine\DBAL\ParameterType::INTEGER,
            'resource_ids' => $connection::PARAM_INT_ARRAY,
        ];
        $allValues = $connection->executeQuery($qb, $bind, $types)->fetchFirstColumn();

        $result = [];
        $percent = null;
        $lowerValue = mb_strtolower($value);

        switch ($method) {
            default:
                return [];
            case 'similar_text':
                foreach ($allValues as $oneValue) {
                    similar_text($lowerValue, mb_strtolower($oneValue), $percent);
                    if ($percent > 66) {
                        $result[] = $oneValue;
                    }
                }
                break;
            case 'levenshtein':
                foreach ($allValues as $oneValue) {
                    if (levenshtein($lowerValue, mb_strtolower($oneValue)) < 10) {
                        $result[] = $oneValue;
                    }
                }
                break;
            case 'metaphone':
                $code = metaphone($lowerValue);
                foreach ($allValues as $oneValue) {
                    if ($code === metaphone(mb_strtolower($oneValue))) {
                        $result[] = $oneValue;
                    }
                }
                break;
            case 'soundex':
                $code = soundex($lowerValue);
                foreach ($allValues as $oneValue) {
                    if ($code === soundex(mb_strtolower($oneValue))) {
                        $result[] = $oneValue;
                    }
                }
                break;
        }

        return $result;
    }
}
