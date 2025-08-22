Dédoublonnage (module pour Omeka S)
===================================

> __Les nouvelles versions de ce modules et l’assistance pour Omeka S version 3.0
> et supérieur sont disponibles sur [GitLab], qui semble mieux respecter les
> utilisateurs et la vie privée que le précédent entrepôt.__

See [English readme].

[Dédoublonnage] est un module pour [Omeka S] qui permet de rechercher des
ressources doublonnées en fonction d’une valeur et de les fusionner. La
suppression des doublons peut également être automatique, seule la première
ressource étant conservée.

En mode manuel, la recherche de doublon peut se faire de façon stricte ou selon
des heuristiques :

- [Texte similaire]
  Le nombre de caractères correspondant est calculé en trouvant la première plus
  longue sous-chaîne commune, puis de même pour les préfixes et les suffixes, de
  façon récursive. La longueur de toutes les sous-chaînes communes sont ajoutées.
- [Distance de Levenshtein]
  La distance correspond au nombre minimal de caractères qu’il faut supprimer,
  insérer ou remplacer pour passer d’une chaîne à l’autre.
- [Soundex]
  Algorithme phonétique d’indexation de noms par leur prononciation en anglais
  britannique.
- [Metaphone]
  Version améliorée de Soundex.


Installation
------------

Consulter la documentation utilisateur pour [installer un module].

Ce module requiert les modules [Common] et [Advanced Search], qui doivent être
installés en premier.

* À partir du zip

Télécharger la dernière livraison [Deduplicate.zip] de la liste des livraisons
et décompresser dans le dossier du module `Deduplicate`.

* Depuis les source set pour le développement

Si le module est installé depuis les sources, renommer le dossier du module en
`Deduplicate`.

Installer ensuite le module comme tous les autres modules Omeka et suivez les
instructions.


Usage
-----

* Mode manuel

- Cliquer sur « Dédoublonnage » dans le menu de gauche, sous les modules.
- Choisir la propriété et la valeur à rechercher.
- Cliquer sur « Rechercher ».
- Une nouvelle page affiche les notices de toutes les ressources correspondant à
  la requête et dont la valeur est la même ou similaire.
- Cliquer la ressource à conserver et celles à fusionner.
- Cliquer sur « Fusionner ».

Les ressources cochées seront supprimées et les ressources liées seront attachées
à la ressource choisie.

Pour traiter d’autres types de ressources que les contenus ou pour pré-filtrer
ces derniers, aller à la page parcourir et faire une recherche puis cliquer sur
« Dédoublonner les ressources » dans le sélecteur de traitement en lot, ou choisir
certaines ressources et choisir « Dédoublonner les ressources sélectionnées » et
saisir une valeur.

* Mode automatique

Choisissez une propriété et cliquer sur "Rechercher", vérifiez les résultats
puis cliquez sur "Dédoublonner".

Les ressources avec plusieurs valeurs en doublon sont ignorées et ne peuvent pas
être dédoublonnées.


TODO
----

- [ ] Fusionner les propriétés choisies (par ligne) ou par valeur (par ressource).


Avertissement
-------------

À utiliser à vos propres risques.

Il est toujours recommandé de sauvegarder vos fichiers et vos bases de données
et de vérifier vos archives régulièrement afin de pouvoir les reconstituer si
nécessaire.


Dépannage
---------

Voir les problèmes en ligne sur la page des [questions du module] du GitLab.


Licence
-------

Ce module est publié sous la licence [CeCILL v2.1], compatible avec [GNU/GPL] et
approuvée par la [FSF] et l’[OSI].

Ce logiciel est régi par la licence CeCILL de droit français et respecte les
règles de distribution des logiciels libres. Vous pouvez utiliser, modifier
et/ou redistribuer le logiciel selon les termes de la licence CeCILL telle que
diffusée par le CEA, le CNRS et l’INRIA à l’URL suivante "http://www.cecill.info".

En contrepartie de l’accès au code source et des droits de copie, de
modification et de redistribution accordée par la licence, les utilisateurs ne
bénéficient que d’une garantie limitée et l’auteur du logiciel, le détenteur des
droits patrimoniaux, et les concédants successifs n’ont qu’une responsabilité
limitée.

À cet égard, l’attention de l’utilisateur est attirée sur les risques liés au
chargement, à l’utilisation, à la modification et/ou au développement ou à la
reproduction du logiciel par l’utilisateur compte tenu de son statut spécifique
de logiciel libre, qui peut signifier qu’il est compliqué à manipuler, et qui
signifie donc aussi qu’il est réservé aux développeurs et aux professionnels
expérimentés ayant des connaissances informatiques approfondies. Les
utilisateurs sont donc encouragés à charger et à tester l’adéquation du logiciel
à leurs besoins dans des conditions permettant d’assurer la sécurité de leurs
systèmes et/ou de leurs données et, plus généralement, à l’utiliser et à
l’exploiter dans les mêmes conditions en matière de sécurité.

Le fait que vous lisez actuellement ce document signifie que vous avez pris
connaissance de la licence CeCILL et que vous en acceptez les termes.


Copyright
---------

* Copyright Daniel Berthereau, 2022-2025

Ces fonctionnalités ont été conçues pour la bibliothèque numérique [Manioc] de
l’Université des Antilles et de l’Université de la Guyane, anciennement gérée
avec [Greenstone].


[Dédoublonnage]: https://gitlab.com/Daniel-KM/Omeka-S-module-Deduplicate
[English readme]: https://gitlab.com/Daniel-KM/Omeka-S-module-Deduplicate/-/blob/master/README.md
[Omeka S]: https://omeka.org/s
[Texte similaire]: https://www.php.net/manual/fr/function.similar-text
[Distance de Levenshtein]: https://fr.wikipedia.org/wiki/Distance_de_Levenshtein
[Soundex]: https://fr.wikipedia.org/wiki/Soundex
[Metaphone]: https://fr.wikipedia.org/wiki/Metaphone
[installer un module]: https://omeka.org/s/docs/user-manual/modules/#installing-modules
[Common]: https://gitlab.com/Daniel-KM/Omeka-S-module-Common
[Advanced Search]: https://gitlab.com/Daniel-KM/Omeka-S-module-AdvancedSearch
[questions du module]: https://gitlab.com/Daniel-KM/Omeka-S-module-Deduplicate/-/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[Manioc]: http://www.manioc.org
[Greenstone]: http://www.greenstone.org
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
