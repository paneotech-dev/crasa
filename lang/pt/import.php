<?php

/*
 * Bulk import and export, RP-08 and BUILD_SPEC 6.2.
 *
 * Errors here name the row, the column and the reason. CLAUDE.md's own
 * example of a good error is exactly that shape.
 */

return [
    'title' => 'Importação em massa',
    'note' => 'Uma folha de cálculo à entrada, verificada antes de escrever seja o que for.',
    'step_template' => 'Descarregar um modelo',
    'step_template_note' => 'Pré-preenchido com os nomes, referências e unidades dos indicadores.',
    'template_all' => 'Todos os indicadores',
    'template_hint' => 'Preencha as colunas de valor e fonte. Deixe uma linha vazia se não tiver nada a reportar: uma linha vazia não é um erro, é publicada como Não reportado.',
    'step_upload' => 'Carregar o ficheiro',
    'step_upload_note' => 'Ainda não se escreve nada. O ficheiro é lido e analisado primeiro.',
    'file' => 'Ficheiro CSV',
    'file_hint' => 'O modelo que descarregou, preenchido. Até 2 MB.',
    'check_action' => 'Verificar o ficheiro',
    'step_report' => 'O que o ficheiro contém',
    'step_export' => 'Exportar este retorno',
    'step_export_note' => 'Os valores tal como estão, em CSV ou JSON. RP-08.',
    'export_csv' => 'Exportar em CSV',
    'export_json' => 'Exportar em JSON',
    'col_indicator' => 'Indicador',
    'col_slug' => 'Referência',
    'col_unit' => 'Unidade',
    'col_value' => 'Valor',
    'col_source_note' => 'Fonte',
    'col_row' => 'Linha',
    'col_column' => 'Coluna',
    'col_reason' => 'Motivo',
    'errors_found' => '{1} Uma linha não pode ser importada.|[2,*] :count linhas não podem ser importadas.',
    'errors_body' => 'Nada foi escrito. Corrija o ficheiro e carregue-o de novo: um conjunto de dados importado pela metade é pior do que um recusado, porque ninguém sabe que metade é real.',
    'error_no_slug' => 'Sem referência de indicador nesta linha.',
    'error_unknown_slug' => 'Nenhum indicador tem a referência :slug. Use o modelo em vez de escrever uma referência.',
    'error_not_a_number' => 'Tem de ser um número em :unit. Encontrado ":found".',
    'error_not_a_boolean' => 'Tem de ser sim ou não. Encontrado ":found".',
    'error_no_source' => 'Um valor precisa de uma fonte. Diga de onde veio, para que possa ser defendido quando um Estado membro o questionar.',
    'ready' => '{1} Uma linha está pronta a importar.|[2,*] :count linhas estão prontas a importar.',
    'commit_hint' => 'Ou todas as linhas são escritas, ou nenhuma. Os valores existentes para os mesmos indicadores são substituídos.',
    'commit_action' => 'Importar estas linhas',
    'committed' => '{1} Um valor importado.|[2,*] :count valores importados.',
    'has_errors' => 'Este ficheiro tem erros, pelo que nada foi escrito. Corrija-o e carregue-o de novo.',
    'expired' => 'O ficheiro verificado já não está nesta sessão. Carregue-o de novo.',
];
