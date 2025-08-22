Deduplicate (module for Omeka S)
================================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

Voir le [Lisez-moi] en français.

[Deduplicate] is a module for [Omeka S] that allows to search for duplicate
resources based on a value and to merge them. The deletion of duplicates can be
done automatically, only the first resource is kept.

In manual mode, the search of duplicate resources can be done strictly or with
heuristics:

- [Similar text]
  The number of matching characters is calculated by finding the longest first
  common substring, and then doing this for the prefixes and the suffixes,
  recursively. The lengths of all found common substrings are added.
- [Levenshtein distance]
  The distance is the minimum number of single-character edits (insertions,
  deletions or substitutions) required to change one word into the other.
- [Soundex]
  Phonetic algorithm for indexing names by sound, as pronounced in British
  English.
- [Metaphone]
  Improved version of Soundex.


Installation
------------

See general end user documentation for [installing a module].

This module requires the modules [Common] and [Advanced Search], that should be
installed first.

* From the zip

Download the last release [Deduplicate.zip] from the list of releases, and
uncompress it in the `modules` directory.

* From the source and for development

If the module was installed from the source, rename the name of the folder of
the module to `Deduplicate`.

Then install it like any other Omeka module and follow the config instructions.


Usage
-----

* Manual mode

- Click on "Deduplicate" in the left menu, under modules.
- Select the property and fill the value to check.
- Click "Submit".
- A new page displays the records of all resources matching the query, if any,
  and whose value is the same or nearly the same.
- Click the resource you want to keep and the ones you want to merge.
- Click on "Merge".

The resources that are checked will be removed and all linked resources of these
removed resources will be attached to the selected resource.

To process other resource types than items or to filter the resources on which
the search is done, go to a resource browse page and do a query then click on
"Deduplicate resources" in the batch edit dropdown or select some resources and
select "Deduplicate selected resources" and fill the form.

* Automatic mode

Select a property, then click on "Search", check results, and click on
"Deduplicate".

Resources with multiple duplicated values are skipped and cannot be
deduplicated.


TODO
----

- [ ] Merge selected properties (by row) or selected value (by resource).


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page.


License
-------

This module is published under the [CeCILL v2.1] license, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

This software is governed by the CeCILL license under French law and abiding by
the rules of distribution of free software. You can use, modify and/ or
redistribute the software under the terms of the CeCILL license as circulated by
CEA, CNRS and INRIA at the following URL "http://www.cecill.info".

As a counterpart to the access to the source code and rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software’s author, the holder of the economic rights, and the
successive licensors have only limited liability.

In this respect, the user’s attention is drawn to the risks associated with
loading, using, modifying and/or developing or reproducing the software by the
user in light of its specific status of free software, that may mean that it is
complicated to manipulate, and that also therefore means that it is reserved for
developers and experienced professionals having in-depth computer knowledge.
Users are therefore encouraged to load and test the software’s suitability as
regards their requirements in conditions enabling the security of their systems
and/or data to be ensured and, more generally, to use and operate it in the same
conditions as regards security.

The fact that you are presently reading this means that you have had knowledge
of the CeCILL license and that you accept its terms.


Copyright
---------

* Copyright Daniel Berthereau, 2022-2025

These features were built for the digital library [Manioc] of the
Université des Antilles and Université de la Guyane, previously managed with
[Greenstone].


[Deduplicate]: https://gitlab.com/Daniel-KM/Omeka-S-module-Deduplicate
[Lisez-moi]: https://gitlab.com/Daniel-KM/Omeka-S-module-Deduplicate/-/blob/master/LISEZMOI.md
[Omeka S]: https://omeka.org/s
[Similar text]: https://www.php.net/manual/en/function.similar-text
[Levenshtein distance]: https://en.wikipedia.org/wiki/Levenshtein_distance
[Soundex]: https://en.wikipedia.org/wiki/Soundex
[Metaphone]: https://en.wikipedia.org/wiki/Metaphone
[installing a module]: https://omeka.org/s/docs/user-manual/modules/#installing-modules
[Common]: https://gitlab.com/Daniel-KM/Omeka-S-module-Common
[Advanced Search]: https://gitlab.com/Daniel-KM/Omeka-S-module-AdvancedSearch
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-Deduplicate/-/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[Manioc]: http://www.manioc.org
[Greenstone]: http://www.greenstone.org
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
