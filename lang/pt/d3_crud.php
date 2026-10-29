<?php

/*
 * Day 3: back-office create, edit and delete for member states,
 * authorities, committees, indicators and instruments.
 */
return [
    'delete_refused' => ':thing tem :list; mova ou elimine estes registos primeiro.',
    'and' => 'e',
    'this_countries' => 'Este Estado membro',
    'this_authorities' => 'Esta autoridade',
    'this_committees' => 'Este comité',
    'this_indicators' => 'Este indicador',
    'this_instruments' => 'Este instrumento',

    'ref_authorities' => ':count autoridade|:count autoridades',
    'ref_instruments' => ':count instrumento|:count instrumentos',
    'ref_indicator_values' => ':count valor reportado|:count valores reportados',
    'ref_submissions' => ':count declaração de dados|:count declarações de dados',
    'ref_committee_seats' => ':count lugar de Estado membro|:count lugares de Estados membros',
    'ref_people_seats' => ':count membro do comité|:count membros do comité',
    'ref_users' => ':count conta de utilizador|:count contas de utilizador',
    'ref_folders' => ':count pasta do espaço de trabalho|:count pastas do espaço de trabalho',
    'ref_documents' => ':count documento|:count documentos',
    'ref_publications' => ':count publicação|:count publicações',
    'ref_delegate_registrations' => ':count inscrição de delegado|:count inscrições de delegados',
    'ref_enquiries' => ':count pedido de informação|:count pedidos de informação',
    'ref_chaired_committees' => ':count comité que preside|:count comités que preside',
    'ref_contacts' => ':count contacto|:count contactos',
    'ref_proposed_changes' => ':count alteração proposta|:count alterações propostas',
    'ref_meetings' => ':count reunião|:count reuniões',
    'ref_announcements' => ':count anúncio|:count anúncios',
    'ref_projects' => ':count projeto que lidera|:count projetos que lidera',
    'ref_submission_values' => ':count valor numa declaração|:count valores em declarações',
    'ref_submission_comments' => ':count comentário de verificação|:count comentários de verificação',
    'ref_superseded_instruments' => ':count instrumento que substitui|:count instrumentos que substitui',

    'reference_data_note' => 'Outros registos apontam para estes. Uma linha pode ser corrigida aqui, e eliminada só quando nada aponta para ela: a eliminação indica o que ainda aponta.',

    'confirm_action' => 'Confirmar o registo',
    'confirmed_by' => 'Confirmado por :name em :date',
    'not_confirmed' => 'Ainda não confirmado. Use Confirmar o registo para registar quem o garante.',


    'localised_title' => 'Francês e português',
    'localised_note' => 'Vazio, aplica-se a tradução própria da plataforma quando existe, depois o inglês.',
    'localised_from_file' => 'Se vazio:',

    'people_title' => 'Membros do comité',
    'people_count' => '{0}Nenhum membro registado|{1}Um membro|[2,*]:count membros',
    'people_empty_title' => 'Nenhum membro registado',
    'people_empty_body' => 'Adicione cada pessoa que integra este comité a partir das contas de utilizador da plataforma, e indique a presidência.',
    'people_col_person' => 'Pessoa',
    'people_role_for' => 'Função de :name',
    'people_remove_confirm' => 'Retirar :name do comité :committee?',
    'people_add' => 'Adicionar um membro',
    'people_add_action' => 'Adicionar o membro',
    'people_chair_hint' => 'Um comité tem uma só presidência. Indicar uma segunda passa a primeira a membro.',
    'member_added' => ':name adicionado como :role.',
    'member_changed' => ':name passa a ser :role.',
    'member_removed' => ':name retirado do comité.',
    'role_member' => 'Membro',
    'role_chair' => 'Presidência',
    'role_observer' => 'Observador',
];
