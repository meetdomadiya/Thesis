<?php declare(strict_types=1);

namespace Guest\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\Form\Element as OmekaElement;

class SiteSettingsFieldset extends Fieldset
{
    /**
     * @var string
     */
    protected $label = 'Guest'; // @translate

    protected $elementGroups = [
        'guest' => 'Guest', // @translate
    ];

    public function init(): void
    {
        $this
            ->setAttribute('id', 'guest')
            ->setOption('element_groups', $this->elementGroups)
            ->add([
                'name' => 'guest_notify_register',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Emails to notify registrations', // @translate
                    'info' => 'The list of emails to notify when a user registers, one by row.', // @translate
                ],
                'attributes' => [
                    'required' => false,
                    'placeholder' => <<<'TXT'
                        contact@example.org
                        info@example2.org
                        TXT,
                ],
            ])

            ->add([
                'name' => 'guest_login_text',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Log in Text', // @translate
                    'info' => 'The text to use for the "Log in" link in the user bar', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_login_text',
                ],
            ])

            ->add([
                'name' => 'guest_register_text',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Register Text', // @translate
                    'info' => 'The text to use for the "Register" link in the user bar', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_register_text',
                ],
            ])

            ->add([
                'name' => 'guest_login_without_form',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Hide the login form (for sso)', // @translate
                    'info' => 'This option allows to display only the links for the other modules, like Single Sign-On.', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_login_without_form',
                ],
            ])

            ->add([
                'name' => 'guest_login_with_register',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Display register form on login page', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_login_with_register',
                ],
            ])

            ->add([
                'name' => 'guest_login_html_before',
                'type' => OmekaElement\CkeditorInline::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Html to display before the login form', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_login_html_before',
                ],
            ])

            ->add([
                'name' => 'guest_login_html_after',
                'type' => OmekaElement\CkeditorInline::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Text to display after the login form', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_login_html_after',
                ],
            ])

            ->add([
                'name' => 'guest_register_html_before',
                'type' => OmekaElement\CkeditorInline::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Html to display before the registration form', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_register_html_before',
                ],
            ])

            ->add([
                'name' => 'guest_register_html_after',
                'type' => OmekaElement\CkeditorInline::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Text to display after the registration form', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_register_html_after',
                ],
            ])

            ->add([
                'name' => 'guest_dashboard_label',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Dashboard Label', // @translate
                    'info' => 'The text to use for the label on the user’s dashboard', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_dashboard_label',
                ],
            ])

            ->add([
                'name' => 'guest_capabilities',
                'type' => OmekaElement\CkeditorInline::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Registration Features', // @translate
                    'info' => 'Add some text to the registration screen so people will know what they get for registering. As you enable and configure plugins that make use of the guest, please give them guidance about what they can and cannot do.', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_capabilities',
                ],
            ])

            /* // From Omeka classic, but not used.
            ->add([
                'name' => 'guest_short_capabilities',
                'type' => OmekaElement\CkeditorInline::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Short Registration Features', // @translate
                    'info' => 'Add a shorter version to use as a dropdown from the user bar. If empty, no dropdown will appear.', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_short_capabilities',
                ],
            ])
            */

            ->add([
                'name' => 'guest_message_notify_registration_email_subject',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Subject of the email sent to administrator to notify about a new user', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_message_notify_registration_email_subject',
                    'placeholder' => '[{site_title}] A user is registering', // @translate
                ],
            ])
            ->add([
                'name' => 'guest_message_notify_registration_email',
                'type' => OmekaElement\CkeditorInline::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Email sent to administrator to notify about a new user', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_message_notify_registration_email',
                    'placeholder' => <<<'MAIL'
                        Hi,
                        A new user, {user_name}, is registering on <a href="{site_url}">{site_title}</a> ({main_title}).
                        You may moderate this email: {user_email}.
                        This is an automatic message.
                        MAIL, // @translate
                ],
            ])

            ->add([
                'name' => 'guest_message_confirm_email_subject',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Subject of the email sent to confirm email', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_message_confirm_email_subject',
                    'placeholder' => '[{site_title}] Confirm email', // @translate
                ],
            ])
            ->add([
                'name' => 'guest_message_confirm_email',
                'type' => OmekaElement\CkeditorInline::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Email sent to confirm registration', // @translate
                    'info' => 'The text of the email to confirm the registration and to send the token.', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_message_confirm_email',
                    'placeholder' => <<<'MAIL'
                        Hi {user_name},
                        You have registered for an account on {main_title} / {site_title} ({site_url}).
                        Please confirm your registration by following this link: {token_url}.
                        If you did not request to join {main_title} please disregard this email.
                        MAIL, // @translate
                ],
            ])

            ->add([
                'name' => 'guest_message_confirm_registration_email_subject',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Subject of the email sent to confirm registration after moderation', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_message_confirm_registration_email_subject',
                    'placeholder' => '[{site_title}] Account open', // @translate
                ],
            ])
            ->add([
                'name' => 'guest_message_confirm_registration_email',
                'type' => OmekaElement\CkeditorInline::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Email sent to confirm registration after moderation', // @translate
                    'info' => 'When the moderation is set, the user may be informed automatically when the admin activates account (check box in second user tab).', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_message_confirm_registration_email',
                    'placeholder' => <<<'MAIL'
                        Hi {user_name},
                        We are happy to open your account on {main_title} / {site_title} ({site_url}).
                        You can now log in and discover the site.
                        MAIL, // @translate
                ],
            ])

            ->add([
                'name' => 'guest_message_update_email_subject',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Subject of email sent to update email', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_message_update_email_subject',
                    'placeholder' => '[{site_title}] Confirm email', // @translate
                ],
            ])
            ->add([
                'name' => 'guest_message_update_email',
                'type' => OmekaElement\CkeditorInline::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Email sent to update email', // @translate
                    'info' => 'The text of the email sent when the user wants to update it.', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_message_update_email',
                    'placeholder' => <<<'MAIL'
                        Hi {user_name},
                        You have requested to update email on {main_title} / {site_title} ({site_url}).
                        Please confirm your email by following this link: {token_url}.
                        If you did not request to update your email on {main_title}, please disregard this email.
                        MAIL, // @translate
                ],
            ])

            ->add([
                'name' => 'guest_message_confirm_email_site',
                'type' => Element\Textarea::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Message to confirm email on the page', // @translate
                    'info' => 'The message to  display after confirmation of a mail.', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_message_confirm_email_site',
                    'required' => false,
                    'placeholder' => 'Your email "{user_email}" is confirmed for {site_title}.', // @translate
                    'rows' => 3,
                ],
            ])
            ->add([
                'name' => 'guest_message_confirm_register_site',
                'type' => Element\Textarea::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Message to confirm registration on the page', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_message_confirm_register_site',
                    'required' => false,
                    'placeholder' => 'Thank you for registering. Please check your email for a confirmation message. Once you have confirmed your request, you will be able to log in.', // @translate
                    'rows' => 3,
                ],
            ])
            ->add([
                'name' => 'guest_message_confirm_register_moderate_site',
                'type' => Element\Textarea::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Message to confirm registration and moderation on the page', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_message_confirm_register_moderate_site',
                    'required' => false,
                    'placeholder' => 'Thank you for registering. Please check your email for a confirmation message. Once you have confirmed your request and we have confirmed it, you will be able to log in.', // @translate
                    'rows' => 3,
                ],
            ])

            ->add([
                'name' => 'guest_terms_text',
                'type' => OmekaElement\CkeditorInline::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Text for terms and conditions', // @translate
                    'info' => 'The text to display to accept condtions.', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_terms_text',
                    'rows' => 12,
                ],
            ])

            ->add([
                'name' => 'guest_terms_page',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Page slug of the terms and conditions', // @translate
                    'info' => 'If the text is on a specific page, or for other usage.', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_terms_page',
                ],
            ])

            ->add([
                'name' => 'guest_redirect',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Redirect page after logging in', // @translate
                    'info' => 'Set "home" for home page (admin or public), "site" for the current site home, "top" for main public page, "me" for guest account, or any path starting with "/", including "/" itself for main home page.', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_redirect',
                    'required' => false,
                ],
            ])

            ->add([
                'name' => 'guest_show_user_bar_for_guest',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'guest',
                    'label' => 'Display user bar for guests (require Omeka option)', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_show_user_bar_for_guest',
                ],
            ])
        ;
    }
}
