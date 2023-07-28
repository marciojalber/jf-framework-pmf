<?php

return [
    'when_using_resource_as_data_query' =>
        'Não é possível utilizar um recurso ' .
        'como argumentos de uma consulta.',

    'missing_informed_index' =>
        'Não foi encontrado o campo "%s" para indexar o resultado da consulta.',

    'invalid_schema' =>
        'Não foi possível conectar no banco-de-dados "%s" do esquema "%s" no ambiente "%s": %s.',

    'missing_schema_config' =>
        'Esquema de conexão "%s" não definido.',

    'target_path_not_informed' =>
        'Informe uma pasta de destino para o backup do esquema "%s".',

    'migration_unexecuted' =>
        'Não foi possível ler os dados do esquema "%s".',

    'model_not_informed' =>
        'Modelo da tabela não informado para construção da expressão SQL.',

    'key_not_exists' =>
        'A propriedade "%s", indicada como chave-primária do model "%s", não existe.',

    'column_not_exists_to_validate' =>
        'A coluna "%s" não foi declarada no DTO "%s".',
];
