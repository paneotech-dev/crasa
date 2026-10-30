<?php

// Dia 3, ponto 1e: acréscimos à inscrição de delegados.
return [
    // Formulário
    'language_legend' => 'Língua',
    'preferred_language' => 'Língua preferida',
    'interpretation' => 'Interpretação',
    'interpretation_hint' => 'Escolha uma língua se precisar de interpretação dos trabalhos. O Secretariado confirma o que pode ser assegurado.',
    'interp_none' => 'Sem interpretação',
    'interp_into_en' => 'Para inglês',
    'interp_into_fr' => 'Para francês',
    'interp_into_pt' => 'Para português',
    'lang_en' => 'Inglês',
    'lang_fr' => 'Francês',
    'lang_pt' => 'Português',

    // Lista da administração
    'all_countries' => 'Todos os países',
    'language_col' => 'Língua',
    'bulk_label' => 'Inscrições seleccionadas',
    'select' => 'Seleccionar',
    'select_all' => 'Seleccionar tudo',
    'select_one' => 'Seleccionar :name',
    'confirm_selected' => 'Confirmar seleccionadas',
    'decline_selected' => 'Recusar seleccionadas',
    'bulk_none_selected' => 'Nenhuma inscrição está seleccionada. Marque pelo menos uma linha e depois confirme ou recuse.',
    'bulk_done_confirmed' => '{0} Nenhuma inscrição alterada: as seleccionadas já estavam confirmadas.|{1} 1 inscrição confirmada.|[2,*] :count inscrições confirmadas.',
    'bulk_done_declined' => '{0} Nenhuma inscrição alterada: as seleccionadas já estavam recusadas.|{1} 1 inscrição recusada.|[2,*] :count inscrições recusadas.',

    // Definições
    'settings_group_registrations' => 'Inscrições de delegados',
    'settings_group_registrations_note' => 'Para onde vai a cópia de cada inscrição.',
    'setting_registrations_mailbox' => 'Caixa das inscrições',
    'setting_registrations_mailbox_hint' => 'Recebe uma cópia de cada inscrição de delegado. Se ficar vazio, a cópia vai para o endereço de correio de contacto.',

    // Cópia para a caixa
    'copy_subject' => 'Inscrição: :name para :meeting',
    'copy_intro' => 'Um delegado inscreveu-se no sítio da CRASA. Esta é a cópia para o Secretariado.',
    'copy_where' => 'Confirme ou recuse na lista de inscrições de delegados:',

    // Estado na página pública
    'postponed_new_date' => 'Adiada, nova data: :date',

    // Aviso aos delegados inscritos
    'change_subject_cancelled' => 'Cancelada: :meeting',
    'change_subject_postponed' => 'Adiada: :meeting',
    'change_subject_new_date' => 'Nova data: :meeting',
    'change_greeting' => 'Caro(a) :name,',
    'change_body_cancelled' => 'A reunião em que se inscreveu foi cancelada.',
    'change_body_postponed' => 'A reunião em que se inscreveu foi adiada. As novas datas serão publicadas na página do evento.',
    'change_body_new_date' => 'A reunião em que se inscreveu tem uma nova data: :date. A sua inscrição mantém-se para a nova data.',
    'change_page' => 'A página do evento:',
];
