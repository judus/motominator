<?php

return [
    'type_overrides' => ['integer' => 'int', 'boolean' => 'bool', 'numeric' => 'numeric-string'],
    'write_query_methods' => false,
    'write_model_magic_where' => false,
    'write_model_external_builder_methods' => false,
    'write_model_relation_count_properties' => false,
    'write_eloquent_model_mixins' => false,
    'enforce_nullable_relationships' => true,
    'soft_deletes_force_nullable' => true,
    'post_migrate' => [],
];
