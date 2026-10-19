<?php

/*
 * Plain-language lines for the audit trail. The action names are stable
 * strings; the verb lives here so a row reads the same whichever language
 * the Secretariat has the interface in.
 */

return [
    'action_created' => 'Création : :subject',
    'action_updated' => 'Modification : :subject',
    'action_deleted' => 'Suppression : :subject',
    'action_login' => 'Connexion',
    'action_login_failed' => 'Une tentative de connexion a échoué',
    'action_logout' => 'Déconnexion',
    'action_role_changed' => 'Changement d\'un rôle',
    'action_document_downloaded' => 'Téléchargement d\'un document',
    'action_submission_transitioned' => 'Passage d\'une soumission à l\'état suivant',
    'action_two_factor_enrolled' => 'Inscription à l\'authentification à deux facteurs',
    'action_two_factor_failed' => 'Un code à deux facteurs a été refusé',
    'action_two_factor_disabled' => 'Désactivation de l\'authentification à deux facteurs',
    'action_recovery_code_used' => 'Utilisation d\'un code de récupération',
    'action_password_changed' => 'Changement d\'un mot de passe',
    'action_password_reset' => 'Réinitialisation d\'un mot de passe',
    'action_suspended' => 'Suspension d\'un compte',
    'action_invitation_accepted' => "Acceptation d'une invitation",
    'action_reactivated' => 'Réactivation d\'un compte',
    'subject_page' => 'une page',
    'subject_pageblock' => 'un bloc',
    'subject_publication' => 'une publication',
    'subject_newsitem' => 'une actualité',
    'subject_instrument' => 'un instrument réglementaire',
    'subject_instrumentversion' => 'une version d\'instrument',
    'subject_document' => 'un document',
    'subject_user' => 'un compte utilisateur',
    'subject_indicator' => 'un indicateur',
    'subject_indicatorvalue' => 'une valeur d\'indicateur',
    'subject_committee' => 'un comité',
    'subject_authority' => 'une autorité',
    'subject_country' => 'un État membre',
    'subject_translation' => 'une traduction',
    'subject_enquiry' => 'une demande',
    'subject_tag' => 'une étiquette',
    'subject_sector' => 'un secteur',
];
