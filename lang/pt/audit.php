<?php

/*
 * Plain-language lines for the audit trail. The action names are stable
 * strings; the verb lives here so a row reads the same whichever language
 * the Secretariat has the interface in.
 */

return [
    'action_created' => 'Criação: :subject',
    'action_updated' => 'Alteração: :subject',
    'action_deleted' => 'Eliminação: :subject',
    'action_login' => 'Autenticação',
    'action_login_failed' => 'Uma tentativa de autenticação falhou',
    'action_logout' => 'Terminou a sessão',
    'action_role_changed' => 'Alteração de uma função',
    'action_document_downloaded' => 'Descarregamento de um documento',
    'action_submission_transitioned' => 'Passagem de uma submissão ao estado seguinte',
    'action_two_factor_enrolled' => 'Inscrição na autenticação de dois fatores',
    'action_two_factor_failed' => 'Um código de dois fatores foi recusado',
    'action_two_factor_disabled' => 'Desativação da autenticação de dois fatores',
    'action_recovery_code_used' => 'Utilização de um código de recuperação',
    'action_password_changed' => 'Alteração de uma palavra-passe',
    'action_password_reset' => 'Reposição de uma palavra-passe',
    'action_suspended' => 'Suspensão de uma conta',
    'action_reactivated' => 'Reativação de uma conta',
    'subject_page' => 'uma página',
    'subject_pageblock' => 'um bloco',
    'subject_publication' => 'uma publicação',
    'subject_newsitem' => 'uma notícia',
    'subject_instrument' => 'um instrumento regulatório',
    'subject_instrumentversion' => 'uma versão de instrumento',
    'subject_document' => 'um documento',
    'subject_user' => 'uma conta de utilizador',
    'subject_indicator' => 'um indicador',
    'subject_indicatorvalue' => 'um valor de indicador',
    'subject_committee' => 'um comité',
    'subject_authority' => 'uma autoridade',
    'subject_country' => 'um Estado membro',
    'subject_translation' => 'uma tradução',
    'subject_enquiry' => 'um pedido',
    'subject_tag' => 'uma etiqueta',
    'subject_sector' => 'um setor',
];
