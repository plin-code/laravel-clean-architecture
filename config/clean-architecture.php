<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Clean Architecture Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for the Laravel Clean
    | Architecture package.
    |
    */

    /*
    | Root namespace of the generated code.
    */
    'default_namespace' => 'App',

    /*
    | Paths of the three layers, relative to the base path. clean-arch:install
    | and the make-* commands write the generated classes there, with
    | namespaces derived from these paths, and clean-arch:make-arch-rules
    | decides what to scan from them.
    */
    'directories' => [
        'domain'         => 'app/Domain',
        'application'    => 'app/Application',
        'infrastructure' => 'app/Infrastructure',
    ],

    'validation' => [
        'custom_messages' => true,

        /*
        | Rules written by clean-arch:make-arch-rules into phparkitect.php. Every
        | rule is enabled by default, set one to false to leave it out of the
        | generated file, then regenerate it with --force. For example, teams
        | that treat a console command as an input adapter and keep it next to
        | the HTTP controllers can turn off 'no_commands_in_infrastructure'.
        */
        'rules' => [
            'domain_no_application_imports'         => true,
            'domain_no_infrastructure_imports'      => true,
            'application_no_infrastructure_imports' => true,
            'no_observers_in_domain'                => true,
            'no_jobs_in_infrastructure'             => true,
            'no_commands_in_infrastructure'         => true,
        ],
    ],

    /*
    | Options read by clean-arch:make-domain and the individual make-* commands
    | when they generate code.
    */
    'generation' => [
        /*
        | When true (the default) generated services and actions extend the
        | BaseService and BaseAction classes created by clean-arch:install.
        | Pass --no-base to the command, or set this to false, to generate
        | standalone classes without a base class.
        */
        'extend_base_classes' => true,

        /*
        | Subfolder the domain model is generated under, inside each domain's
        | directory. Defaults to 'Models', so clean-arch:make-domain writes
        | app/Domain/Articles/Models/Article.php declaring
        | App\Domain\Articles\Models\Article. Set it to null or an empty
        | string to generate the model directly inside the domain directory
        | instead, app/Domain/Articles/Article.php declaring
        | App\Domain\Articles\Article. Any other single segment, for example
        | 'Entities', replaces 'Models'.
        */
        'model_directory' => 'Models',
    ],
];
