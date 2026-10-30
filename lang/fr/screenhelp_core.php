<?php

/*
 * Help per screen for the back office and the member area, keyed by route
 * name. Each entry: a title, one sentence on what the screen is for, and its
 * three main actions. Read by App\Support\ScreenHelp with every other
 * screenhelp_*.php file. _back_office and _member are each section's general
 * help, shown for a screen that has no entry of its own.
 */

return [
    '_back_office' => [
        'title' => 'Administration',
        'purpose' => 'L\'administration est l\'endroit où le Secrétariat publie et modère les contenus, valide les rapports des États membres et gère les comptes.',
        'actions' => [
            'Choisissez une section dans la barre latérale : ce que vous voyez dépend de votre rôle.',
            'Utilisez le champ de recherche de la barre supérieure pour trouver n\'importe quel enregistrement de l\'administration.',
            'Ouvrez la cloche pour voir les demandes d\'approbation, les rapports et les résultats de validation qui vous attendent.',
        ],
    ],
    '_member' => [
        'title' => 'Espace membres',
        'purpose' => 'L\'espace membres est l\'endroit où les quatorze États membres et les comités partagent des documents, déposent leurs rapports et suivent les réunions.',
        'actions' => [
            'Choisissez une section dans la barre latérale : vos comités, vos dossiers et vos rapports.',
            'Utilisez le champ de recherche de la barre supérieure pour chercher dans l\'espace membres.',
            'Ouvrez la cloche pour voir les nouveaux documents, les changements de réunion et les résultats de validation.',
        ],
    ],

    /* Help itself ---------------------------------------------------------- */

    'screen-help' => [
        'title' => 'Aide',
        'purpose' => 'Cette page explique l\'écran d\'où vous venez : à quoi il sert et ses trois actions principales.',
        'actions' => [
            'Lisez à quoi sert l\'écran et quelles sont ses actions principales.',
            'Revenez à l\'écran avec Retour à l\'écran.',
            'Ouvrez le guide de formation de votre rôle pour des instructions étape par étape.',
        ],
    ],

    /* Account and notifications ------------------------------------------------ */

    'account' => [
        'title' => 'Votre compte',
        'purpose' => 'Vos propres informations : nom, langue, mot de passe et manière dont la plateforme vous contacte.',
        'actions' => [
            'Changez la langue de l\'interface et vos préférences d\'affichage.',
            'Changez votre mot de passe.',
            'Déconnectez-vous de la plateforme.',
        ],
    ],
    'two-factor.enrol' => [
        'title' => 'Connexion à deux facteurs',
        'purpose' => 'Ajoute une seconde étape à la connexion avec une application d\'authentification, ce qui protège les comptes qui administrent la plateforme.',
        'actions' => [
            'Scannez le code avec une application d\'authentification sur votre téléphone.',
            'Saisissez le code à six chiffres affiché par l\'application pour confirmer.',
            'Conservez les codes de récupération en lieu sûr.',
        ],
    ],
    'my-dashboard' => [
        'title' => 'Mon tableau de bord',
        'purpose' => 'Votre page d\'accueil : les derniers éléments publiés et un accès au comparateur.',
        'actions' => [
            'Ouvrez les dernières publications, actualités et mises à jour réglementaires.',
            'Allez au comparateur pour comparer des indicateurs entre États membres.',
            'Ouvrez votre compte pour changer votre langue ou votre mot de passe.',
        ],
    ],
    'notifications' => [
        'title' => 'Notifications',
        'purpose' => 'Tout ce que la plateforme vous a signalé : demandes d\'approbation, rapports, résultats de validation, nouveaux documents, changements de réunion et inscriptions confirmées.',
        'actions' => [
            'Filtrez la liste par type, ou affichez seulement les notifications non lues.',
            'Ouvrez une notification pour accéder à son élément ; elle est alors marquée comme lue.',
            'Marquez une notification, ou toutes, comme lues.',
        ],
    ],

    /* Back office ------------------------------------------------------------- */

    'admin.dashboard' => [
        'title' => 'Tableau de bord de l\'administration',
        'purpose' => 'La vue d\'ensemble du travail qui attend votre rôle : approbations, rapports à valider, activité récente et données de démonstration encore présentes dans la base.',
        'actions' => [
            'Ouvrez depuis les blocs du tableau de bord un élément qui attend votre décision.',
            'Suivez l\'activité récente sur la plateforme.',
            'Allez à une section depuis la barre latérale.',
        ],
    ],
    'admin.search' => [
        'title' => 'Rechercher dans l\'administration',
        'purpose' => 'Trouve les pages, publications, actualités, événements, utilisateurs et autres enregistrements de l\'administration.',
        'actions' => [
            'Saisissez un mot ou une référence dans le champ de recherche.',
            'Affinez les résultats par type.',
            'Ouvrez un résultat pour le modifier.',
        ],
    ],
    'admin.help' => [
        'title' => 'Présentation de l\'administration',
        'purpose' => 'Une courte présentation de l\'administration et du point de départ de chaque section, que vous pouvez relire à tout moment.',
        'actions' => [
            'Lisez où commence chaque section.',
            'Rétablissez les conseils que vous avez masqués sur d\'autres écrans.',
            'Ouvrez le guide de formation de votre rôle.',
        ],
    ],
    'admin.approvals' => [
        'title' => 'Approbations',
        'purpose' => 'Les éléments soumis à approbation qui attendent une décision, limités aux types de contenu que vous pouvez approuver.',
        'actions' => [
            'Ouvrez un élément pour le lire avec ses modifications.',
            'Approuvez-le pour le publier, ou renvoyez-le à son auteur avec un commentaire.',
            'Filtrez la liste par type de contenu.',
        ],
    ],
    'admin.approvals.show' => [
        'title' => 'Demande d\'approbation',
        'purpose' => 'Un élément soumis à approbation : son contenu tel qu\'il a été soumis, ce qui a changé et la note de l\'auteur.',
        'actions' => [
            'Lisez l\'aperçu et la liste des modifications.',
            'Approuvez l\'élément pour le publier.',
            'Renvoyez-le à son auteur avec un commentaire indiquant ce qu\'il faut changer.',
        ],
    ],
    'admin.approvals.rules' => [
        'title' => 'Règles d\'approbation',
        'purpose' => 'Définit, pour chaque type de contenu, si la publication exige une approbation et qui peut s\'en dispenser.',
        'actions' => [
            'Choisissez la règle de chaque type de contenu.',
            'Enregistrez les règles.',
            'Vérifiez sur l\'écran des rôles quels rôles disposent de la permission d\'approuver.',
        ],
    ],
    'admin.audit' => [
        'title' => 'Journal d\'audit',
        'purpose' => 'La trace de qui a modifié quoi et quand : chaque publication, approbation, modification de compte et changement d\'état d\'un rapport.',
        'actions' => [
            'Filtrez le journal par utilisateur, objet, action ou date.',
            'Lisez chaque modification : champ, valeur avant et valeur après.',
            'Exportez le journal filtré en CSV.',
        ],
    ],
    'admin.announcements' => [
        'title' => 'Annonces',
        'purpose' => 'Les avis du Secrétariat aux membres, parfois limités à un secteur ou à un comité.',
        'actions' => [
            'Rédigez une nouvelle annonce.',
            'Modifiez ou publiez un brouillon.',
            'Dépubliez une annonce qui n\'est plus d\'actualité.',
        ],
    ],
    'admin.announcements.create' => [
        'title' => 'Nouvelle annonce',
        'purpose' => 'Rédige un avis aux membres en anglais, en français et en portugais.',
        'actions' => [
            'Rédigez le titre et le texte, au moins en anglais.',
            'Choisissez le secteur ou le comité concerné, le cas échéant.',
            'Enregistrez-la comme brouillon, puis publiez-la.',
        ],
    ],
    'admin.announcements.edit' => [
        'title' => 'Modifier l\'annonce',
        'purpose' => 'Modifie une annonce et la publie ; les membres sont notifiés lors de sa publication.',
        'actions' => [
            'Modifiez le texte dans chaque langue.',
            'Publiez l\'annonce, ce qui notifie les membres concernés.',
            'Dépubliez-la lorsqu\'elle n\'est plus d\'actualité.',
        ],
    ],
    'admin.authority-changes' => [
        'title' => 'Modifications des fiches des autorités',
        'purpose' => 'Les modifications de la fiche d\'un régulateur proposées par son État membre, en attente du Secrétariat.',
        'actions' => [
            'Ouvrez une proposition pour la comparer à la fiche actuelle.',
            'Approuvez-la pour mettre à jour la fiche publique.',
            'Refusez-la avec une note à l\'État membre.',
        ],
    ],
    'admin.authority-changes.show' => [
        'title' => 'Modification de fiche proposée',
        'purpose' => 'La modification proposée par un État membre pour la fiche de son régulateur, à côté de la fiche actuelle.',
        'actions' => [
            'Comparez chaque champ proposé à la valeur actuelle.',
            'Approuvez la modification pour l\'appliquer.',
            'Refusez-la avec une note.',
        ],
    ],
    'admin.committees.seats' => [
        'title' => 'Sièges au comité',
        'purpose' => 'Indique quels États membres siègent à un comité, et à quel titre.',
        'actions' => [
            'Ajoutez un État membre au comité.',
            'Définissez son rôle, par exemple président ou membre.',
            'Retirez un siège qui n\'est plus d\'actualité.',
        ],
    ],
    'admin.confirmations' => [
        'title' => 'Confirmations des États membres',
        'purpose' => 'Les fiches des quatorze États membres et l\'indication, pour chacune, de sa confirmation par son propre régulateur.',
        'actions' => [
            'Ouvrez un État membre pour examiner sa fiche.',
            'Voyez quelles fiches ne sont pas encore vérifiées.',
            'Indiquez qui a confirmé une fiche.',
        ],
    ],
    'admin.confirmations.edit' => [
        'title' => 'Confirmer la fiche d\'un État membre',
        'purpose' => 'La fiche du régulateur d\'un État membre, modifiable sur place, avec une action de confirmation qui enregistre qui s\'en est porté garant.',
        'actions' => [
            'Corrigez le nom, le sigle, le site web ou le mandat.',
            'Indiquez la personne qui a confirmé les informations.',
            'Confirmez la fiche.',
        ],
    ],
    'admin.contributions' => [
        'title' => 'Instruments transmis',
        'purpose' => 'Les instruments nationaux transmis par les États membres pour le registre harmonisé, en attente du Secrétariat.',
        'actions' => [
            'Ouvrez une contribution pour l\'examiner.',
            'Validez-la pour l\'intégrer au registre.',
            'Renvoyez-la à l\'État membre avec des commentaires.',
        ],
    ],
    'admin.contributions.show' => [
        'title' => 'Examiner une contribution',
        'purpose' => 'Un instrument transmis : son texte, ses documents et son historique.',
        'actions' => [
            'Lisez l\'instrument et ses documents joints.',
            'Validez-le pour l\'intégrer au registre.',
            'Renvoyez-le avec un commentaire indiquant ce qu\'il faut changer.',
        ],
    ],
    'admin.data' => [
        'title' => 'Contenus',
        'purpose' => 'La liste des types de contenu que vous pouvez gérer : publications, actualités, instruments, projets et autres enregistrements.',
        'actions' => [
            'Choisissez un type de contenu pour voir ses enregistrements.',
            'Créez un nouvel enregistrement de ce type.',
            'Voyez combien d\'enregistrements compte chaque type.',
        ],
    ],
    'admin.resources' => [
        'title' => 'Enregistrements',
        'purpose' => 'Les enregistrements d\'un type de contenu, avec leur statut et leur couverture linguistique.',
        'actions' => [
            'Recherchez et filtrez les enregistrements.',
            'Ouvrez un enregistrement pour le modifier.',
            'Ajoutez un nouvel enregistrement.',
        ],
    ],
    'admin.resources.create' => [
        'title' => 'Nouvel enregistrement',
        'purpose' => 'Crée un enregistrement de ce type de contenu.',
        'actions' => [
            'Remplissez les champs, au moins en anglais.',
            'Enregistrez-le comme brouillon.',
            'Soumettez-le à approbation ou publiez-le, selon ce que permet votre rôle.',
        ],
    ],
    'admin.resources.edit' => [
        'title' => 'Modifier l\'enregistrement',
        'purpose' => 'Modifie un enregistrement ; chaque modification est consignée avec son auteur et son heure.',
        'actions' => [
            'Modifiez les champs et enregistrez.',
            'Soumettez l\'enregistrement à approbation ou publiez-le, selon ce que permet votre rôle.',
            'Supprimez l\'enregistrement s\'il ne devrait pas exister.',
        ],
    ],
    'admin.instruments.versions' => [
        'title' => 'Versions de l\'instrument',
        'purpose' => 'L\'historique des versions d\'un instrument réglementaire : qui l\'a modifié et quand.',
        'actions' => [
            'Lisez la liste des versions.',
            'Comparez une version avec la précédente.',
            'Restaurez une version antérieure si votre rôle le permet.',
        ],
    ],
    'admin.instruments.diff' => [
        'title' => 'Comparer des versions',
        'purpose' => 'Montre, champ par champ, ce qui a changé entre deux versions d\'un instrument.',
        'actions' => [
            'Lisez le texte supprimé et le texte ajouté.',
            'Choisissez d\'autres versions à comparer.',
            'Revenez à l\'historique des versions.',
        ],
    ],
    'admin.media' => [
        'title' => 'Médiathèque',
        'purpose' => 'Les images et fichiers utilisés sur le site public, avec l\'endroit où chacun est utilisé.',
        'actions' => [
            'Téléversez une nouvelle image ou un nouveau fichier.',
            'Ouvrez un élément pour modifier sa description et son crédit.',
            'Voyez où un élément est utilisé avant de le remplacer.',
        ],
    ],
    'admin.media.create' => [
        'title' => 'Téléverser un média',
        'purpose' => 'Ajoute une image ou un fichier à la médiathèque.',
        'actions' => [
            'Choisissez le fichier à téléverser.',
            'Rédigez sa description pour les lecteurs d\'écran et son crédit.',
            'Enregistrez-le dans la médiathèque.',
        ],
    ],
    'admin.media.edit' => [
        'title' => 'Modifier le média',
        'purpose' => 'Modifie la description, le crédit et le recadrage d\'une image ou d\'un fichier.',
        'actions' => [
            'Modifiez la description et le crédit.',
            'Voyez les pages qui l\'utilisent.',
            'Remplacez-le ou supprimez-le.',
        ],
    ],
    'admin.meetings' => [
        'title' => 'Réunions',
        'purpose' => 'Les réunions et événements du calendrier, à venir et passés, avec leurs inscriptions.',
        'actions' => [
            'Ajoutez une réunion.',
            'Ouvrez une réunion pour changer sa date, son lieu ou son statut.',
            'Ouvrez la liste des personnes inscrites à une réunion.',
        ],
    ],
    'admin.meetings.create' => [
        'title' => 'Nouvelle réunion',
        'purpose' => 'Ajoute une réunion ou un événement au calendrier.',
        'actions' => [
            'Saisissez le titre, les dates et le lieu.',
            'Choisissez le comité et qui peut la voir.',
            'Enregistrez-la, ou soumettez-la à approbation.',
        ],
    ],
    'admin.meetings.edit' => [
        'title' => 'Modifier la réunion',
        'purpose' => 'Modifie une réunion ; les membres du comité et les personnes inscrites sont notifiés lorsqu\'elle est déplacée ou annulée.',
        'actions' => [
            'Modifiez les dates, le lieu ou le lien en ligne.',
            'Passez le statut à reportée ou annulée.',
            'Enregistrez la modification.',
        ],
    ],
    'admin.meetings.delete' => [
        'title' => 'Supprimer la réunion',
        'purpose' => 'Confirme qu\'une réunion doit être retirée du calendrier, avec ses documents.',
        'actions' => [
            'Vérifiez qu\'il s\'agit bien de la réunion à retirer.',
            'Envisagez plutôt de la passer à annulée, ce qui conserve l\'enregistrement.',
            'Confirmez la suppression.',
        ],
    ],
    'admin.delegates' => [
        'title' => 'Inscriptions',
        'purpose' => 'Les personnes inscrites à une réunion, avec le statut de chaque inscription.',
        'actions' => [
            'Triez et lisez la liste des inscriptions.',
            'Confirmez ou refusez une inscription.',
            'Téléchargez la liste sous forme de tableur.',
        ],
    ],
    'admin.menus' => [
        'title' => 'Menus',
        'purpose' => 'Les menus du site public, avec leur ordre et leurs libellés dans chaque langue.',
        'actions' => [
            'Ajoutez un élément à un menu.',
            'Changez l\'ordre des éléments.',
            'Changez le libellé d\'un élément en anglais, en français et en portugais.',
        ],
    ],
    'admin.pages' => [
        'title' => 'Pages',
        'purpose' => 'Les pages du site public, avec leur statut et leur couverture linguistique.',
        'actions' => [
            'Ouvrez une page pour la modifier.',
            'Ajoutez une nouvelle page.',
            'Filtrez les pages par statut.',
        ],
    ],
    'admin.pages.create' => [
        'title' => 'Nouvelle page',
        'purpose' => 'Crée une page sur le site public.',
        'actions' => [
            'Saisissez le titre et l\'adresse.',
            'Choisissez sa place dans le site.',
            'Enregistrez-la comme brouillon et ajoutez ses blocs.',
        ],
    ],
    'admin.pages.edit' => [
        'title' => 'Modifier la page',
        'purpose' => 'Modifie le texte et les blocs d\'une page ; un élément en cours d\'examen s\'ouvre en lecture seule.',
        'actions' => [
            'Modifiez le texte et l\'ordre des blocs.',
            'Prévisualisez la page.',
            'Soumettez-la à approbation ou publiez-la, selon ce que permet votre rôle.',
        ],
    ],
    'admin.pages.preview' => [
        'title' => 'Aperçu de la page',
        'purpose' => 'Montre la page telle qu\'elle apparaîtra sur le site public, avant sa publication.',
        'actions' => [
            'Lisez la page telle qu\'un visiteur la verra.',
            'Vérifiez-la à la largeur d\'un téléphone.',
            'Revenez à l\'éditeur pour la modifier.',
        ],
    ],
    'admin.pages.blocks.create' => [
        'title' => 'Ajouter un bloc',
        'purpose' => 'Ajoute un bloc à une page, par exemple un texte, une image ou une liste.',
        'actions' => [
            'Choisissez le type de bloc.',
            'Remplissez son contenu.',
            'Enregistrez-le dans la page.',
        ],
    ],
    'admin.pages.blocks.edit' => [
        'title' => 'Modifier un bloc',
        'purpose' => 'Modifie un bloc d\'une page.',
        'actions' => [
            'Modifiez le contenu du bloc.',
            'Enregistrez le bloc.',
            'Retirez le bloc de la page.',
        ],
    ],
    'admin.reports' => [
        'title' => 'Notes régionales',
        'purpose' => 'Des notes produites à partir des indicateurs, par État membre ou par indicateur, conservées pour pouvoir être retrouvées.',
        'actions' => [
            'Produisez une note pour un État membre ou un indicateur.',
            'Ouvrez une note conservée.',
            'Imprimez une note ou téléchargez-la en PDF.',
        ],
    ],
    'admin.reports.show' => [
        'title' => 'Note',
        'purpose' => 'Une note conservée, avec les chiffres à partir desquels elle a été produite.',
        'actions' => [
            'Lisez la note.',
            'Téléchargez-la en PDF.',
            'Revenez à la liste des notes.',
        ],
    ],
    'admin.roles' => [
        'title' => 'Rôles',
        'purpose' => 'Les rôles de la plateforme et ce que chacun peut faire.',
        'actions' => [
            'Lisez les permissions de chaque rôle.',
            'Ouvrez un rôle pour modifier ses permissions.',
            'Créez un nouveau rôle.',
        ],
    ],
    'admin.roles.create' => [
        'title' => 'Nouveau rôle',
        'purpose' => 'Crée un rôle doté de son propre ensemble de permissions.',
        'actions' => [
            'Nommez le rôle.',
            'Cochez les permissions qu\'il détient.',
            'Enregistrez le rôle.',
        ],
    ],
    'admin.roles.edit' => [
        'title' => 'Modifier le rôle',
        'purpose' => 'Modifie ce qu\'un rôle peut faire ; les autres administrateurs sont notifiés.',
        'actions' => [
            'Cochez ou décochez des permissions.',
            'Enregistrez la modification.',
            'Vérifiez sur l\'écran des utilisateurs qui détient ce rôle.',
        ],
    ],
    'admin.samples' => [
        'title' => 'Données de démonstration',
        'purpose' => 'Les données de démonstration encore présentes dans la base, table par table, avant leur suppression pour la mise en service.',
        'actions' => [
            'Lisez combien de lignes de démonstration chaque table contient.',
            'Vérifiez quels enregistrements sont encore marqués comme données de démonstration.',
            'Demandez à l\'équipe technique de lancer la suppression lorsque cela ne présente plus de risque.',
        ],
    ],
    'admin.settings' => [
        'title' => 'Paramètres',
        'purpose' => 'Les paramètres généraux de la plateforme, comme les coordonnées, la messagerie et les comptes de réseaux sociaux.',
        'actions' => [
            'Modifiez un paramètre.',
            'Vérifiez si l\'envoi de courriels est activé.',
            'Enregistrez les paramètres.',
        ],
    ],
    'admin.social' => [
        'title' => 'Publications sur les réseaux sociaux',
        'purpose' => 'Les publications des réseaux sociaux affichées sur le site public.',
        'actions' => [
            'Ajoutez une publication.',
            'Ouvrez une publication pour la modifier.',
            'Soumettez une publication à approbation ou publiez-la.',
        ],
    ],
    'admin.social.create' => [
        'title' => 'Nouvelle publication sur les réseaux sociaux',
        'purpose' => 'Ajoute une publication des réseaux sociaux à afficher sur le site public.',
        'actions' => [
            'Collez l\'adresse de la publication.',
            'Rédigez la légende.',
            'Enregistrez-la, ou soumettez-la à approbation.',
        ],
    ],
    'admin.social.edit' => [
        'title' => 'Modifier la publication sur les réseaux sociaux',
        'purpose' => 'Modifie une publication des réseaux sociaux affichée sur le site public.',
        'actions' => [
            'Modifiez la légende ou l\'adresse.',
            'Enregistrez la modification.',
            'Supprimez la publication.',
        ],
    ],
    'admin.submissions' => [
        'title' => 'Déclarations annuelles',
        'purpose' => 'Les rapports des États membres et leur état : brouillon, déposé, validé ou renvoyé.',
        'actions' => [
            'Filtrez les rapports par État membre, période ou état.',
            'Ouvrez un rapport déposé pour l\'examiner.',
            'Voyez quels États membres n\'ont pas encore déposé leur rapport.',
        ],
    ],
    'admin.submissions.show' => [
        'title' => 'Examiner un rapport',
        'purpose' => 'Le rapport d\'un État membre, indicateur par indicateur, avec des commentaires sur les champs.',
        'actions' => [
            'Lisez chaque chiffre et sa source.',
            'Commentez un champ qui doit être modifié.',
            'Validez le rapport, ou renvoyez-le à l\'État membre.',
        ],
    ],
    'admin.translations' => [
        'title' => 'Traductions',
        'purpose' => 'Les contenus dont le texte français ou portugais manque.',
        'actions' => [
            'Filtrez par type de contenu ou par langue.',
            'Ouvrez un élément pour le traduire.',
            'Prévisualisez une traduction avant de l\'enregistrer.',
        ],
    ],
    'admin.translations.edit' => [
        'title' => 'Traduire',
        'purpose' => 'Le texte anglais à côté du texte français ou portugais, champ par champ.',
        'actions' => [
            'Rédigez ou corrigez la traduction de chaque champ.',
            'Prévisualisez la page traduite.',
            'Enregistrez la traduction.',
        ],
    ],
    'admin.translations.preview' => [
        'title' => 'Aperçu de la traduction',
        'purpose' => 'Montre l\'élément traduit tel qu\'un lecteur de cette langue le verra.',
        'actions' => [
            'Lisez l\'élément traduit.',
            'Vérifiez la mise en page avec le texte plus long.',
            'Revenez à la traduction pour la corriger.',
        ],
    ],
    'admin.users' => [
        'title' => 'Utilisateurs',
        'purpose' => 'Les comptes de la plateforme, avec leurs rôles, leurs États membres et leur statut.',
        'actions' => [
            'Invitez un nouvel utilisateur.',
            'Changez le rôle ou l\'État membre d\'un utilisateur.',
            'Suspendez ou réactivez un compte.',
        ],
    ],
    'admin.users.form' => [
        'title' => 'Inviter un utilisateur',
        'purpose' => 'Crée un compte et enregistre l\'invitation destinée à son titulaire.',
        'actions' => [
            'Saisissez le nom et l\'adresse électronique.',
            'Choisissez le rôle et l\'État membre.',
            'Envoyez l\'invitation.',
        ],
    ],

    /* Member area ------------------------------------------------------------- */

    'extranet.dashboard' => [
        'title' => 'Tableau de bord de l\'espace membres',
        'purpose' => 'Votre page d\'accueil dans l\'espace membres : vos comités, vos dossiers, vos rapports et ce qui a changé.',
        'actions' => [
            'Ouvrez un comité ou un dossier.',
            'Voyez l\'état du rapport de votre État membre.',
            'Lisez les dernières annonces et notifications.',
        ],
    ],
    'extranet.folders' => [
        'title' => 'Dossiers',
        'purpose' => 'Les dossiers de documents que vous pouvez ouvrir, par comité et par État membre.',
        'actions' => [
            'Ouvrez un dossier pour voir ses documents.',
            'Voyez le niveau d\'accès de chaque dossier.',
            'Recherchez un document dans l\'ensemble de vos dossiers.',
        ],
    ],
    'extranet.folder' => [
        'title' => 'Dossier',
        'purpose' => 'Les documents d\'un dossier, au niveau d\'accès que permettent votre rôle et votre siège.',
        'actions' => [
            'Ouvrez ou téléchargez un document.',
            'Téléversez un document, si votre rôle le permet.',
            'Revenez à l\'ensemble des dossiers.',
        ],
    ],
    'extranet.committee' => [
        'title' => 'Espace du comité',
        'purpose' => 'Les membres, les documents et les réunions d\'un comité.',
        'actions' => [
            'Lisez les derniers documents du comité.',
            'Voyez ses prochaines réunions.',
            'Voyez quels États membres y siègent.',
        ],
    ],
    'extranet.calendar' => [
        'title' => 'Calendrier',
        'purpose' => 'Les réunions de vos comités et de la région, mois par mois.',
        'actions' => [
            'Passez d\'un mois à l\'autre.',
            'Ouvrez une réunion pour en voir les détails.',
            'Ajoutez le calendrier à votre propre application d\'agenda.',
        ],
    ],
    'extranet.search' => [
        'title' => 'Rechercher dans l\'espace membres',
        'purpose' => 'Trouve les documents, dossiers et enregistrements de l\'espace membres que vous pouvez ouvrir.',
        'actions' => [
            'Saisissez un mot ou une référence.',
            'Affinez les résultats par type.',
            'Ouvrez un résultat.',
        ],
    ],
    'announcements' => [
        'title' => 'Annonces',
        'purpose' => 'Les avis du Secrétariat aux membres.',
        'actions' => [
            'Ouvrez une annonce pour la lire.',
            'Voyez à quel secteur ou comité elle s\'adresse.',
            'Revenez à votre tableau de bord.',
        ],
    ],
    'announcements.show' => [
        'title' => 'Annonce',
        'purpose' => 'Un avis du Secrétariat.',
        'actions' => [
            'Lisez l\'annonce.',
            'Ouvrez les documents vers lesquels elle renvoie.',
            'Revenez à l\'ensemble des annonces.',
        ],
    ],
    'authority' => [
        'title' => 'Votre autorité',
        'purpose' => 'La fiche du régulateur de votre État membre telle que le public la voit, et un moyen de proposer des corrections.',
        'actions' => [
            'Vérifiez la fiche présentée au public.',
            'Proposez une correction d\'un champ.',
            'Envoyez la proposition au Secrétariat.',
        ],
    ],
    'completeness' => [
        'title' => 'Complétude des données',
        'purpose' => 'Quels États membres ont communiqué quels indicateurs, période par période.',
        'actions' => [
            'Lisez la matrice des États membres et des indicateurs.',
            'Choisissez une période.',
            'Ouvrez la version en tableau des chiffres.',
        ],
    ],
    'contributions' => [
        'title' => 'Vos contributions',
        'purpose' => 'Les instruments nationaux que votre État membre a transmis, ou prépare, pour le registre harmonisé.',
        'actions' => [
            'Commencez une nouvelle contribution.',
            'Ouvrez un brouillon pour le poursuivre.',
            'Voyez la décision du Secrétariat sur chacune.',
        ],
    ],
    'contributions.create' => [
        'title' => 'Nouvelle contribution',
        'purpose' => 'Décrit un instrument national pour le registre harmonisé.',
        'actions' => [
            'Saisissez la référence, le type, le secteur et le statut.',
            'Rédigez le titre et le résumé.',
            'Enregistrez-la comme brouillon.',
        ],
    ],
    'contributions.edit' => [
        'title' => 'Modifier la contribution',
        'purpose' => 'Modifie une contribution et l\'envoie au Secrétariat.',
        'actions' => [
            'Corrigez les informations et joignez le document.',
            'Enregistrez le brouillon.',
            'Soumettez-la au Secrétariat pour validation.',
        ],
    ],
    'directory' => [
        'title' => 'Annuaire des membres',
        'purpose' => 'Les contacts des quatorze régulateurs qui ont accepté d\'y figurer, réservé aux membres.',
        'actions' => [
            'Recherchez par nom, autorité ou secteur.',
            'Ajoutez un contact pour votre autorité.',
            'Corrigez ou retirez un contact dont vous êtes responsable.',
        ],
    ],
    'indicators.reference' => [
        'title' => 'Définitions des indicateurs',
        'purpose' => 'Ce que signifie chaque indicateur du rapport et comment le renseigner.',
        'actions' => [
            'Lisez la définition et l\'unité d\'un indicateur.',
            'Filtrez par catégorie.',
            'Allez à votre rapport.',
        ],
    ],
    'register' => [
        'title' => 'Registre harmonisé',
        'purpose' => 'Les instruments réglementaires des quatorze États membres, consultables par chaque membre.',
        'actions' => [
            'Recherchez et filtrez par État membre, secteur ou type.',
            'Ouvrez un instrument.',
            'Comparez des instruments entre États membres.',
        ],
    ],
    'register.show' => [
        'title' => 'Instrument',
        'purpose' => 'Un instrument du registre harmonisé, avec ses documents et les instruments liés.',
        'actions' => [
            'Lisez les informations de l\'instrument.',
            'Ouvrez ses documents.',
            'Ouvrez un instrument lié.',
        ],
    ],
    'submissions' => [
        'title' => 'Déclaration annuelle',
        'purpose' => 'Les déclarations annuelles de votre État membre, période par période, et leur état.',
        'actions' => [
            'Soumettez une nouvelle déclaration pour une période.',
            'Ouvrez une déclaration en brouillon ou renvoyée pour la compléter.',
            'Exportez une déclaration en CSV ou en JSON.',
        ],
    ],
    'submissions.edit' => [
        'title' => 'Déclaration annuelle, période, État membre',
        'purpose' => 'Une déclaration annuelle, indicateur par indicateur, avec les éventuels commentaires du Secrétariat.',
        'actions' => [
            'Saisissez ou corrigez chaque chiffre avec sa source.',
            'Lisez les commentaires du Secrétariat sur les champs renvoyés.',
            'Soumettez la déclaration pour validation.',
        ],
    ],
    'submissions.import' => [
        'title' => 'Importer un rapport',
        'purpose' => 'Remplit un rapport à partir d\'un tableur, en deux étapes : contrôle, puis confirmation.',
        'actions' => [
            'Téléchargez le modèle et remplissez-le.',
            'Téléversez le fichier et lisez le résultat du contrôle.',
            'Confirmez l\'import, qui enregistre tout ou rien.',
        ],
    ],
];
