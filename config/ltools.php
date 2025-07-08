<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Changelog
    |--------------------------------------------------------------------------
    |
    | ChangelogItem table name
    |
    */
    'table_name_changelog_items' => 'ltools_changelog_items',

    /*
    |--------------------------------------------------------------------------
    | Fields excluded from changelogs for all entities throughout the project.
    |--------------------------------------------------------------------------
    */
    'global_excluded_changelog_fields' => ['created_at', 'updated_at'],

    /*
    |--------------------------------------------------------------------------
    | User model class for correct user relation
    |--------------------------------------------------------------------------
    */
    'user_model_class' => \App\Models\User::class,

];
