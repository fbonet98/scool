<?php

use App\Http\Resources\UserResource;
use App\Models\Address;
use App\Models\AdministrativeStatus;
use App\Models\Course;
use App\Models\Department;
use App\Models\Family;
use App\Models\Force;
use App\Models\Identifier;
use App\Models\IdentifierType;
use App\Models\Law;
use App\Models\Lesson;
use App\Models\Location;
use App\Models\Menu;
use App\Models\PendingTeacher;
use App\Models\Position;
use App\Models\Province;
use App\Models\Specialty;
use App\Models\Job;
use App\Models\JobType;
use App\Models\Subject;
use App\Models\SubjectGroup;
use App\Models\Teacher;
use App\Models\User;
use App\Models\UserType;
use App\Models\WeekLesson;
use App\Repositories\PersonRepository;
use App\Repositories\TeacherRepository;
use App\Repositories\UserRepository;
use App\Revisionable\Revision;
use App\Study;
use App\Tenant;
use Carbon\Carbon;
use PulkitJalan\Google\Client;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Create model.
 *
 * @param $class
 * @param array $attributes
 * @param int $times
 * @return mixed
 */
function create($class, $attributes = [], $times = 1)
{
    $data = factory($class)->times($times)->create($attributes);
    if ($times > 1) {
        return $data;
    }
    return $data->first();
}

if (! function_exists('scool_menu')) {

    /**
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    function scool_menu()
    {
        return Menu::all();
    }
}

if (! function_exists('create_tenant')) {

    /**
     * Create Tenant.
     *
     * @param $name
     * @param $subdomain
     * @return $this|\Illuminate\Database\Eloquent\Model
     */
    function create_tenant($name,$subdomain)
    {
        return Tenant::create([
            'name' => $name,
            'subdomain' => $subdomain,
            'hostname' => 'localhost',
            'username' => $subdomain,
            'password' => 'secret',
            'database' => $subdomain,
            'port' => 3306
        ]);
    }
}

if (! function_exists('tenant_connect')) {
    /**
     * Establish a tenant database connection.
     *
     * @param $hostname
     * @param $username
     * @param $password
     * @param $database
     */
    function tenant_connect($hostname, $username, $password, $database)
    {
        // Erase the tenant connection, thus making Laravel get the default values all over again.
        DB::purge('tenant');

        // Make sure to use the database name we want to establish a connection.
        Config::set('database.connections.tenant.host', $hostname);
        Config::set('database.connections.tenant.database', $database);
        Config::set('database.connections.tenant.username', $username);
        Config::set('database.connections.tenant.password', $password);

        // Rearrange the connection data
        DB::reconnect('tenant');

        // Ping the database. This will throw an exception in case the database does not exists.
        Schema::connection('tenant')->getConnection()->reconnect();
    }
}

if (! function_exists('main_connect')) {
    function main_connect()
    {
        // Erase the tenant connection, thus making Laravel get the default values all over again.
        DB::purge('tenant');

        Config::set('database.default',env('DB_CONNECTION', 'mysql'));

        // Ping the database. This will throw an exception in case the database does not exists.
        Schema::connection(config('database.default'))->getConnection()->reconnect();
    }
}

if (! function_exists('create_admin_user_on_tenant')) {
    /**
     * @param $user
     * @param $tenant
     */
    function create_admin_user_on_tenant($user, $tenant, $password = null)
    {
        tenant_connect(
            $tenant->hostname,
            $tenant->username,
            $tenant->password,
            $tenant->database
        );

        if(!$password) $password = str_random();

        $existingUser = App\Models\User::where('email',$user->email)->first();

        if (!$existingUser) {
            User::forceCreate([
                'name' => $user->name,
                'email' => $user->email,
                'password' => bcrypt($password),
                'admin' => true
            ]);
        }
        DB::purge('tenant');
    }
}

if (! function_exists('create_admin_user')) {
    /**
     *
     */
    function create_admin_user()
    {
        if (! App\User::where('email',env('ADMIN_USER_EMAIL','sergiturbadenas@gmail.com'))->first()) {
            App\User::forceCreate([
                'name' => env('ADMIN_USER_NAME','Sergi Tur Badenas'),
                'email' => env('ADMIN_USER_EMAIL','sergiturbadenas@gmail.com'),
                'password' => bcrypt(env('ADMIN_USER_PASSWORD','123456')),
                'admin' => true
            ]);
        }
    }
}

if (! function_exists('create_tenant_admin_user')) {
    /**
     *
     */
    function create_tenant_admin_user()
    {
        if (! App\Models\User::where('email',env('ADMIN_USER_EMAIL','sergiturbadenas@gmail.com'))->first()) {
            App\Models\User::forceCreate([
                'name' => env('ADMIN_USER_NAME','Sergi Tur Badenas'),
                'email' => env('ADMIN_USER_EMAIL','sergiturbadenas@gmail.com'),
                'password' => env('ADMIN_USER_PASSWORD','123456'), // 123456 en sha1
                'admin' => true
            ]);
        }
    }
}

if (! function_exists('create_other_tenant_admin_users')) {
    /**
     *
     */
    function create_other_tenant_admin_users()
    {
        if (! App\Models\User::where('email',env('ADMIN_USER_EMAIL1','dmontero@iesebre.com'))->first()) {
            App\Models\User::forceCreate([
                'name' => env('ADMIN_USER_NAME1','Dídac Montero Borràs'),
                'email' => env('ADMIN_USER_EMAIL1','dmontero@iesebre.com'),
                'password' => env('ADMIN_USER_PASSWORD1','7c4a8d09ca3762af61e59520943dc26494f8941b'),
                'admin' => true
            ]);
        }

        if (! App\Models\User::where('email',env('ADMIN_USER_EMAIL2','fbonet@iesebre.com'))->first()) {
            App\Models\User::forceCreate([
                'name' => env('ADMIN_USER_NAME2','Ferran Bonet Iborra'),
                'email' => env('ADMIN_USER_EMAIL2','fbonet@iesebre.com'),
                'password' => env('ADMIN_USER_PASSWORD2','7c4a8d09ca3762af61e59520943dc26494f8941b'),
                'admin' => true
            ]);
        }
    }
}

if (! function_exists('create_default_tenant')) {
    function create_default_tenant() {
        $user = App\User::find(1);
        $tenant = Tenant::where('subdomain','iesebre')->first();
        if (! $tenant) {
            $tenant = $user->addTenant($tenant = Tenant::create([
                'name' => "Institut de l'Ebre",
                'subdomain' => 'iesebre',
                'hostname' => 'localhost',
                'database' => 'iesebre',
                'username' => 'iesebre',
                'password' => str_random(),
                'port' => 3306,
                'gsuite_service_account_path' => '/gsuite_service_accounts/scool-07eed0b550a6f.json',
                'gsuite_admin_email' => 'sergitur@iesebre.com'
            ]));
        }

        create_mysql_full_database(
            $tenant->database,
            $tenant->username ,
            $tenant->password,
            $tenant->hostname);

        create_admin_user_on_tenant($tenant->user, $tenant, env('ADMIN_USER_PASSWORD','123456'));

        DB::purge('tenant');

        main_connect();
    }
}


if (! function_exists('create_admin_user_on_subdomain')) {

    /**
     * @param $subdomain
     */
    function create_admin_user_on_subdomain($subdomain)
    {
        $tenant = Tenant::findBySubdomain($subdomain);
        tenant_connect(
            $tenant->hostname,
            $tenant->username,
            $tenant->password,
            $tenant->database
        );

        User::forceCreate([
            'name' => env('ADMIN_USER_NAME_ON_TENANT','Sergi Tur Badenas'),
            'email' => env('ADMIN_USER_EMAIL_ON_TENANT','sergiturbadenas@gmail.com'),
            'password' => sha1(env('ADMIN_USER_PASSWORD_ON_TENANT','123456')),
            'admin' => true
        ]);
    }
}

if (! function_exists('save_current_tenant_config')) {
    /**
     * @return object
     */
    function save_current_tenant_config()
    {
        $host = Config::get('database.connections.tenant.host');
        $database = Config::get('database.connections.tenant.database');
        $username = Config::get('database.connections.tenant.username');
        $password = Config::get('database.connections.tenant.password');

        return (object) compact('host', 'database', 'username', 'password');
    }
}

if (! function_exists('restore_current_tenant_config')) {
    /**
     * @param $oldConfig
     */
    function restore_current_tenant_config($oldConfig)
    {
        Config::set('database.connections.tenant.host', $oldConfig->host);
        Config::set('database.connections.tenant.database', $oldConfig->database);
        Config::set('database.connections.tenant.username', $oldConfig->username);
        Config::set('database.connections.tenant.password', $oldConfig->password);
    }
}

if (! function_exists('test_user')) {
    /**
     * @param $user
     * @param $tenant
     * @return array
     */
    function test_user($user, $tenant, $password) {
        $current_config = save_current_tenant_config();
        $result = [];
        try {
            tenant_connect($tenant->hostname, $tenant->username, $tenant->password, $tenant->database);

            $tenantUser = User::where('email',$user->email)->firstOrFail();

            if (Hash::check($password, $tenantUser->password)) {
                $result = [ 'connection' => 'ok' ];
            } else {
                $result = [
                    'connection' => 'Error',
                    'exception' => 'Password incorrect for user ' . $user->email
                ];
            }


        } catch (PDOException $e) {
            $result = [
                'connection' => 'Error',
                'exception' => $e->getMessage()
            ];
        }

        restore_current_tenant_config($current_config);
        return $result;
    }
}

if (! function_exists('test_connection')) {
    /**
     * @param $hostname
     * @param $username
     * @param $password
     * @param $database
     * @return array
     */
    function test_connection($hostname, $username, $password, $database)
    {
        $current_config = save_current_tenant_config();
        $result = [];
        try {
            tenant_connect($hostname, $username, $password, $database);
            $result = [ 'connection' => 'ok' ];
        } catch (PDOException $e) {
            $result = [
                'connection' => 'Error',
                'exception' => $e->getMessage()
            ];
        }
        restore_current_tenant_config($current_config);

        return $result;
    }
}


if (! function_exists('tenant_migrate')) {
    /**
     * Run Tenant Migrations in the connected tenant database.
     */
    function tenant_migrate()
    {
        Config::set('auth.providers.users.model',User::class);

        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant'
        ]);
    }
}

if (! function_exists('tenant_seed')) {
    /**
     * Run Tenant Migrations in the connected tenant database.
     */
    function tenant_seed()
    {
        Config::set('auth.providers.users.model',User::class);

        Artisan::call('db:seed', [
            '--database' => 'tenant',
            '--class' => 'TenantDatabaseSeeder'
        ]);
    }
}

/**
 * @param $name
 * @param $user
 * @param null $password
 * @return null|string
 */
function create_mysql_full_database($name, $user , $password = null, $host = null)
{
    create_mysql_database($name);
    $password = create_mysql_user($user, $password, $host);
    mysql_grant_privileges($user, $name, $host);

    $hostname = 'localhost';
    if ($host) $hostname = $host;
    tenant_connect($hostname, $name, $password, $name);
    tenant_migrate();
    tenant_seed();

    return $password;
}

if (! function_exists('tenant_connect_migrate_seed')) {

    /**
     * @param $name
     */
    function tenant_connect_migrate_seed($name)
    {
        $tenant = Tenant::findBySubDomain($name);
        tenant_connect($tenant->hostname, $tenant->subdomain, $tenant->password, $tenant->subdomain);
        tenant_migrate();
        tenant_seed();
    }
}

/**
 * @param $name
 * @param $user
 * @param null $password
 * @return null|string
 */
function delete_mysql_full_database($name, $user, $host = null)
{
    delete_mysql_database($name);
    delete_mysql_user($user, $host);
}

/**
 *
 */
function set_mysql_admin_connection() {
    DB::purge('mysql');

    Config::set('database.connections.mysql.host', env('MYSQL_ADMIN_HOST'));
    Config::set('database.connections.mysql.port', env('MYSQL_ADMIN_PORT'));
    Config::set('database.connections.mysql.database', null);
    Config::set('database.connections.mysql.username', env('MYSQL_ADMIN_USERNAME'));
    Config::set('database.connections.mysql.password', env('MYSQL_ADMIN_PASSWORD'));

    // Rearrange the connection data
    DB::reconnect('mysql');

    // Ping the database. This will throw an exception in case the database does not exists.
    Schema::connection('mysql')->getConnection()->reconnect();
}

/**
 * @param $name
 */
function create_mysql_database($name)
{
    set_mysql_admin_connection();
    DB::connection('mysql')->getPdo()->exec("CREATE DATABASE IF NOT EXISTS `{$name}`");
}

/**
 * @param $name
 */
function delete_mysql_database($name)
{
    set_mysql_admin_connection();
    DB::connection('mysql')->getPdo()->exec("DROP DATABASE IF EXISTS `{$name}`");
}

/**
 * @param $name
 */
function remove_mysql_database($name)
{
    set_mysql_admin_connection();
    DB::connection('mysql')->getPdo()->exec("DROP DATABASE IF EXISTS `{$name}`");
}

/**
 * @param $name
 * @param null $password
 * @param string $host
 * @return null|string
 */
function create_mysql_user($name, $password = null, $host = 'localhost')
{
    set_mysql_admin_connection();
    if(!$password) $password = str_random();
    DB::connection('mysql')->getPdo()->exec(
        "CREATE USER IF NOT EXISTS '{$name}'@'{$host}'");
    DB::connection('mysql')->getPdo()->exec(
        "ALTER USER '{$name}'@'{$host}' IDENTIFIED BY '{$password}'");
    return $password;
}

/**
 * @param $name
 * @param null $password
 * @param string $host
 * @return null|string
 */
function delete_mysql_user($name, $host = 'localhost')
{
    set_mysql_admin_connection();
    DB::connection('mysql')->getPdo()->exec(
        "DROP USER IF EXISTS '{$name}'@'{$host}'");
}

/**
 * @param $user
 * @param string $host
 */
function mysql_grant_all_privileges($user, $host = 'localhost') {
    set_mysql_admin_connection();
    DB::connection('mysql')->getPdo()->exec(
        "GRANT ALL PRIVILEGES ON *.* TO '{$user}'@'{$host}' WITH GRANT OPTION");
    DB::connection('mysql')->getPdo()->exec("FLUSH PRIVILEGES");
}

/**
 * @param $user
 * @param $database
 * @param string $host
 */
function mysql_grant_privileges($user, $database, $host = 'localhost') {
    set_mysql_admin_connection();
    DB::connection('mysql')->getPdo()->exec(
        "GRANT ALL PRIVILEGES ON {$database}.* TO '{$user}'@'{$host}' WITH GRANT OPTION");
    DB::connection('mysql')->getPdo()->exec("FLUSH PRIVILEGES");
}

if (!function_exists('get_tenant')) {
    /**
     * @param $name
     * @return mixed
     */
    function get_tenant($name) {
        return \App\Tenant::where('subdomain', $name)->firstOrFail();
    }
}


if (!function_exists('formatted_logged_user')) {
    function formatted_logged_user()
    {
        return json_encode((new UserResource(Auth::user()))->resolve());
    }
}

if (!function_exists('initialize_tenant_roles_and_permissions')) {
    function initialize_tenant_roles_and_permissions()
    {
        $roles = [
            'Student',
            'Teacher',
            'Janitor',
            'AdministrativeAssistant',
            'Familiar',
            'Manager',
            'Admin',
            'UsersManager',
            'StaffManager',
            'TeachersManager',
            'PhotoTeachersManager',
            'LessonsManager'
        ];

        // Manager
        // - Rol assignat a l'usuari principal (de fet és superadmin) però també es pot assignar a altres
        // - Menú administració:
        // - Gestió de mòduls

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        $permissions = [
        ];


        foreach ($permissions as $permission) {
            $permission = Permission::firstOrCreate(['name' => $permission]);
        }
    }
}

if (!function_exists('initialize_gates')) {
    function initialize_gates()
    {
        Gate::define('calculate-lessons', function ($user) {
            return $user->hasRole(['LessonsManager']);
        });

        Gate::define('show-teacher-profile', function ($user) {
            return $user->isTeacher();
        });

        Gate::define('impersonate-user', function ($user) {
            return $user->isSuperAdmin();
        });

        Gate::define('store-user-photo', function ($user) {
            return $user->hasRole(['UsersManager','TeachersManager']);
        });

        Gate::define('approve-teacher', function ($user) {
            return $user->hasRole(['UsersManager','TeachersManager']);
        });

        Gate::define('show-users', function ($user) {
            return $user->hasRole('UsersManager');
        });

        Gate::define('create_users', function ($user) {
            return $user->hasRole('UsersManager');
        });

        Gate::define('delete_users', function ($user) {
            return $user->hasRole('UsersManager');
        });

        // Google suite Users
        Gate::define('list-gsuite-users', function ($user) {
            return $user->hasRole('UsersManager');
        });

        Gate::define('show-gsuite-users', function ($user) {
            return $user->hasRole('UsersManager');
        });

        Gate::define('store-gsuite-users', function ($user) {
            return $user->hasRole('UsersManager');
        });

        Gate::define('delete-gsuite-users', function ($user) {
            return $user->hasRole('UsersManager');
        });

        Gate::define('watch-gsuite-users', function ($user) {
            return $user->hasRole('UsersManager');
        });

        // STAFF/JOBS
        Gate::define('list-available-users', function ($user) {
            return $user->hasRole('StaffManager') || $user->hasRole('TeachersManager');
        });

        Gate::define('list-jobs', function ($user) {
            return $user->hasRole('StaffManager');
        });

        Gate::define('show-jobs', function ($user) {
            return $user->hasRole('StaffManager');
        });

        Gate::define('store-job', function ($user) {
            return $user->hasRole('StaffManager');
        });

        Gate::define('update-job', function ($user) {
            return $user->hasRole('StaffManager');
        });

        Gate::define('delete-job', function ($user) {
            return $user->hasRole('StaffManager');
        });

        Gate::define('store-job-substitutions', function ($user) {
            return $user->hasRole('StaffManager');
        });

        Gate::define('update-job-substitutions', function ($user) {
            return $user->hasRole('StaffManager');
        });

        Gate::define('delete-job-substitution', function ($user) {
            return $user->hasRole('StaffManager');
        });

        Gate::define('delete-job-substitutions', function ($user) {
            return $user->hasRole('StaffManager');
        });

        //Teachers
        Gate::define('list_teachers', function ($user) {
            return $user->hasRole('TeachersManager');
        });

        Gate::define('show-teachers', function ($user) {
            return $user->hasRole('TeachersManager');
        });

        Gate::define('show-pending-teachers', function ($user) {
            return $user->hasRole('TeachersManager');
        });

        Gate::define('delete-pending-teacher', function ($user) {
            return $user->hasRole('TeachersManager');
        });


        Gate::define('show-teachers-photos', function ($user) {
            return $user->hasRole('PhotoTeachersManager');
        });

        Gate::define('store-teachers-photos', function ($user) {
            return $user->hasRole('PhotoTeachersManager');
        });

        Gate::define('show-teacher-photo', function ($user) {
            return $user->hasRole('PhotoTeachersManager');
        });

        Gate::define('download-teacher-photo', function ($user) {
            return $user->hasRole('PhotoTeachersManager');
        });

        Gate::define('delete-teacher-photo', function ($user) {
            return $user->hasRole('PhotoTeachersManager');
        });

        Gate::define('delete-all-teacher-photo', function ($user) {
            return $user->hasRole('PhotoTeachersManager');
        });

        Gate::define('edit-teacher-photo', function ($user) {
            return $user->hasRole('PhotoTeachersManager');
        });

        Gate::define('store-teacher-photo', function ($user) {
            return $user->hasRole('PhotoTeachersManager');
        });

        Gate::define('list-teacher-photo', function ($user) {
            return $user->hasRole('PhotoTeachersManager');
        });

        Gate::define('destroy-teacher-photo', function ($user) {
            return $user->hasRole('PhotoTeachersManager');
        });

        //Pending teachers
        Gate::define('list_pending_teachers', function ($user) {
            return $user->hasRole('TeachersManager');
        });

        Gate::define('show-lessons', function ($user) {
            return $user->hasRole('LessonsManager');
        });

        Gate::before(function ($user, $ability) {
            if ($user->isSuperAdmin()) {
                return true;
            }
        });


    }
}

if (!function_exists('initialize_menus')) {
    function initialize_menus() {
        Menu::firstOrCreate([
            'icon' => 'home',
            'text' => 'Principal',
            'href' => '/home'
        ]);

        Menu::firstOrCreate([
            'heading' => 'Administració',
            'role' => 'Manager'
        ]);

        Menu::firstOrCreate([
            'text' => 'Mòduls',
            'href' => '/modules',
            'role' => 'Manager'
        ]);

        Menu::firstOrCreate([
            'text' => 'Potencial',
            'href' => '/lessons',
            'role' => 'LessonsManager'
        ]);

        Menu::firstOrCreate([
            'text' => 'Usuaris',
            'href' => '/users',
            'role' => 'UsersManager'
        ]);

        Menu::firstOrCreate([
            'text' => 'Plantilla',
            'href' => '/jobs',
            'role' => 'StaffManager'
        ]);

        Menu::firstOrCreate([
            'text' => 'Professorat',
            'href' => '/teachers',
            'role' => 'TeachersManager'
        ]);

        Menu::firstOrCreate([
            'text' => 'Fotos Professorat',
            'href' => '/teachers_photos',
            'role' => 'PhotoTeachersManager'
        ]);


        Menu::firstOrCreate([
            'text' => 'Configuració general',
            'href' => '/config',
            'role' => 'Admin'
        ]);
    }
}



if (!function_exists('initialize_job_types')) {
    function initialize_job_types()
    {
        JobType::firstOrCreate([
            'name' => 'Professor/a'
        ]);

        JobType::firstOrCreate([
            'name' => 'Conserge'
        ]);

        JobType::firstOrCreate([
            'name' => 'Administratiu/va'
        ]);
    }
}

if (!function_exists('initialize_users')) {
    function initialize_users()
    {

    }
}

if (!function_exists('collect_files')) {
    /**
     * Collect files.
     *
     * @param $path
     * @param string $disk
     * @return static
     */
    function collect_files($path, $disk = 'local')
    {
        $files = collect(File::allFiles(Storage::disk($disk)->path($path)))->map(function ($file) {
            return [
                'filename' => $filename = $file->getFilename(),
                'slug' => str_slug($filename,'-')
            ];
        });
        return $files;
    }
}

if (!function_exists('initialize_administrative_assistants')) {
    function initialize_administrative_assistants()
    {
        User::createIfNotExists([
            'name' => 'Pilar Vericat',
            'email' => 'pilarvericat@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('AdministrativeAssistant'))
            ->assignFullName([
                'givenName' => 'Pilar',
                'sn1' => 'Vericat',
                'sn2' => '',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Administratiu/va')->id,
                    'code' => 'A1',
                    'order' => 1
                ])
            );

        User::createIfNotExists([
            'name' => 'Cinta Tomas',
            'email' => 'cintatomas@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('AdministrativeAssistant'))
            ->assignFullName([
                'givenName' => 'Cinta',
                'sn1' => 'Tomas',
                'sn2' => '',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Administratiu/va')->id,
                    'code' => 'A2',
                    'order' => 1
                ])
            );

        User::createIfNotExists([
            'name' => 'Lluïsa Garcia',
            'email' => 'lluisagarcia@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('AdministrativeAssistant'))
            ->assignFullName([
                'givenName' => 'Lluisa',
                'sn1' => 'Garcia',
                'sn2' => '',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Administratiu/va')->id,
                    'code' => 'A3',
                    'order' => 1
                ])
            );

        User::createIfNotExists([
            'name' => 'Sonia Alegria',
            'email' => 'soniaalegria@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('AdministrativeAssistant'))
            ->assignFullName([
                'givenName' => 'Sonia',
                'sn1' => 'Alegria',
                'sn2' => '',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Administratiu/va')->id,
                    'code' => 'F4',
                    'order' => 1
                ])
            );
    }
}

if (!function_exists('initialize_janitors')) {
    function initialize_janitors()
    {
        User::createIfNotExists([
            'name' => 'Jaume Benaiges',
            'email' => 'jaumebenaiges@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret using SHA1 (blames Gsuite) instead of bcrypt
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Janitor'))
            ->assignFullName([
                'givenName' => 'Jaume',
                'sn1' => 'Benaiges',
                'sn2' => '',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Conserge')->id,
                    'code' => 'C1',
                    'order' => 1
                ])
            );

        User::createIfNotExists([
            'name' => 'Jordi Caudet',
            'email' => 'jordicaudet@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Janitor'))
            ->assignFullName([
                'givenName' => 'Jordi',
                'sn1' => 'Caudet',
                'sn2' => '',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Conserge')->id,
                    'code' => 'C2',
                    'order' => 2
                ])
            );

        User::createIfNotExists([
            'name' => 'Leonor Agramunt',
            'email' => 'leonoragramunt@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Janitor'))
            ->assignFullName([
                'givenName' => 'Leonor',
                'sn1' => 'Agramunt',
                'sn2' => '',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Conserge')->id,
                    'code' => 'C3',
                    'order' => 3
                ])
            );
    }
}

if (!function_exists('initialize_teachers_ppas')) {
    function initialize_teachers_ppas()
    {
        User::createIfNotExists([
            'name' => 'Dolors Sanjuan Aubà',
            'email' => 'dolorssanjuanauba@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Dolors',
                'sn1' => 'Sanjuan',
                'sn2' => 'Aubà',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('CAS')->id,
                    'code' => '001',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '002',
                'department_id' => Department::findByCode('PPAS')->id
            ]))->assignPosition(Position::firstOrCreate([
                'name' => 'Tutora de CAM'
            ]));

        User::createIfNotExists([
            'name' => 'Julià Curto De la Vega',
            'email' => 'jcurto@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Julià',
                'sn1' => 'Curto',
                'sn2' => 'De la Vega',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('MA')->id,
                    'code' => '002',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '008',
                'department_id' => Department::findByCode('PPAS')->id
            ]));

        // Principi de curs
        // Sílvia Armengol Bosch (nº 4). Anglès. DNI: 47829022Q. DNI: 47829022Q.Fa la baixa d'Isabel Jordà:
        // sarmeng5@xtec.com (crec que deuria ser .cat; però m'ha posat .com?)
        // TODO ?? No tenim Sílvia Armengol
        User::createIfNotExists([
            'name' => 'Núria Vallés Machirant',
            'email' => 'nuriavalles@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Núria',
                'sn1' => 'Vallés',
                'sn2' => 'Machirant',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('AN')->id,
                    'code' => '003',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '004',
                'department_id' => Department::findByCode('PPAS')->id
            ]));

        User::createIfNotExists([
            'name' => 'Enric Querol Coll',
            'email' => 'equerol@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Enric',
                'sn1' => 'Querol',
                'sn2' => 'Coll',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('AN')->id,
                    'code' => '003',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '005',
                'department_id' => Department::findByCode('PPAS')->id
            ]));

        User::createIfNotExists([
            'name' => 'Lara Melich Cañado',
            'email' => 'laramelich@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Lara',
                'sn1' => 'Melich',
                'sn2' => 'Cañado',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('AN')->id,
                    'code' => '004',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '006',
                'department_id' => Department::findByCode('PPAS')->id
            ]));

        User::createIfNotExists([
            'name' => 'Carme Aznar Pedret',
            'email' => 'carmeaznar@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Carme',
                'sn1' => 'Aznar',
                'sn2' => 'Pedret',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('AN')->id,
                    'code' => '005',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '007',
                'department_id' => Department::findByCode('PPAS')->id
            ]));
    }
}

if (!function_exists('initialize_teachers_fol')) {
    function initialize_teachers_fol()
    {
        User::createIfNotExists([
            'name' => 'Teresa Lasala Descarrega',
            'email' => 'tlasala@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Teresa',
                'sn1' => 'Lasala',
                'sn2' => 'Descarrega',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('505')->id,
                    'code' => '008',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '009',
                'department_id' => Department::findByCode('FOL')->id
            ]));

        User::createIfNotExists([
            'name' => 'Carmina Andreu Pons',
            'email' => 'candreu@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Carmina',
                'sn1' => 'Andreu',
                'sn2' => 'Pons',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('505')->id,
                    'code' => '009',
                    'order' => 2
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '010',
                'department_id' => Department::findByCode('FOL')->id
            ]));

        User::createIfNotExists([
            'name' => 'J.Andrés Brocal Safont',
            'email' => 'jbrocal@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'J.Andrés',
                'sn1' => 'Brocal',
                'sn2' => 'Safont',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('505')->id,
                    'code' => '010',
                    'order' => 3
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '011',
                'department_id' => Department::findByCode('FOL')->id
            ]));

        User::createIfNotExists([
            'name' => 'Pilar Fadurdo Estrada',
            'email' => 'pilarfadurdo@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Pilar',
                'sn1' => 'Fadurdo',
                'sn2' => 'Estrada',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('505')->id,
                    'code' => '011',
                    'order' => 4
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '012',
                'department_id' => Department::findByCode('FOL')->id
            ]));

        User::createIfNotExists([
            'name' => 'Carlos Querol Bel',
            'email' => 'carlosquerol@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Carlos',
                'sn1' => 'Querol',
                'sn2' => 'Bel',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('505')->id,
                    'code' => '012',
                    'order' => 5
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '013',
                'department_id' => Department::findByCode('FOL')->id
            ]));

        User::createIfNotExists([
            'name' => 'Marisa Grau Campeón',
            'email' => 'cgrau@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Marisa',
                'sn1' => 'Grau',
                'sn2' => 'Campeón',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('505')->id,
                    'code' => '013',
                    'order' => 6
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '003',
                'department_id' => Department::findByCode('FOL')->id
            ]));
    }
}

if (!function_exists('initialize_teachers_electrics')) {
    function initialize_teachers_electrics()
    {
        User::createIfNotExists([
            'name' => 'Nuria Bordes Vidal',
            'email' => 'nbordes@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Nuria',
                'sn1' => 'Bordes',
                'sn2' => 'Vidal',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('524')->id,
                    'family_id' => Family::findByCode('ELECTRIC')->id,
                    'code' => '028',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '028',
                'department_id' => Department::findByCode('ELÈCTRICS')->id
            ]));

        User::createIfNotExists([
            'name' => 'Laura Llopis Lozano',
            'email' => 'laurallopis@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Laura',
                'sn1' => 'Llopis',
                'sn2' => 'Lozano',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('525')->id,
                    'family_id' => Family::findByCode('ELECTRIC')->id,
                    'code' => '029',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '029',
                'department_id' => Department::findByCode('ELÈCTRICS')->id
            ]));

        User::createIfNotExists([
            'name' => 'Vicent Favà Figueres',
            'email' => 'vfava@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Vicent',
                'sn1' => 'Favà',
                'sn2' => 'Figueres',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('525')->id,
                    'family_id' => Family::findByCode('ELECTRIC')->id,
                    'code' => '030',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '030',
                'department_id' => Department::findByCode('ELÈCTRICS')->id
            ]));

        User::createIfNotExists([
            'name' => 'Agustí Baubí Rovira',
            'email' => 'agustinbaubi@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Agustí',
                'sn1' => 'Baubí',
                'sn2' => 'Rovira',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('525')->id,
                    'family_id' => Family::findByCode('ELECTRIC')->id,
                    'code' => '031',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '031',
                'department_id' => Department::findByCode('ELÈCTRICS')->id
            ]));

        User::createIfNotExists([
            'name' => 'Josep Joan Cid Castella',
            'email' => 'joancid1@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Josep Joan',
                'sn1' => 'Cid',
                'sn2' => 'Castellar',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('513')->id,
                    'family_id' => Family::findByCode('ELECTRIC')->id,
                    'code' => '116',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '116',
                'department_id' => Department::findByCode('ELÈCTRICS')->id
            ]));

        User::createIfNotExists([
            'name' => 'Rafel Puig Rios',
            'email' => 'rafelpuig@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Rafel',
                'sn1' => 'Puig',
                'sn2' => 'Rios',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('602')->id,
                    'family_id' => Family::findByCode('ELECTRIC')->id,
                    'code' => '032',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '032',
                'department_id' => Department::findByCode('ELÈCTRICS')->id
            ]));

        User::createIfNotExists([
            'name' => 'Laureà Ferré Menasanch',
            'email' => 'lferre@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Laureà',
                'sn1' => 'Ferré',
                'sn2' => 'Menasanch',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('602')->id,
                    'family_id' => Family::findByCode('ELECTRIC')->id,
                    'code' => '033',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '033',
                'department_id' => Department::findByCode('ELÈCTRICS')->id
            ]));

        User::createIfNotExists([
            'name' => 'Manel Canalda Vidal',
            'email' => 'manelcanalda@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Manel',
                'sn1' => 'Canalda',
                'sn2' => 'Vidal',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('605')->id,
                    'family_id' => Family::findByCode('ELECTRIC')->id,
                    'code' => '034',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '034',
                'department_id' => Department::findByCode('ELÈCTRICS')->id
            ]));

        User::createIfNotExists([
            'name' => 'Xavi Bel Fernández',
            'email' => 'xbel@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Xavi',
                'sn1' => 'Bel',
                'sn2' => 'Fernández',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('606')->id,
                    'family_id' => Family::findByCode('ELECTRIC')->id,
                    'code' => '035',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '035',
                'department_id' => Department::findByCode('ELÈCTRICS')->id
            ]));

        // TODO ->
        // A Ebreescool surt Francesc Audí Povedano. Es va jubilar?
        User::createIfNotExists([
            'name' => 'J.Luís Colomé Monllao',
            'email' => 'jcolome@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'J.Luís',
                'sn1' => 'Colomé',
                'sn2' => 'Monllao',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('606')->id,
                    'family_id' => Family::findByCode('ELECTRIC')->id,
                    'code' => '036',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '036',
                'department_id' => Department::findByCode('ELÈCTRICS')->id
            ]));

        User::createIfNotExists([
            'name' => 'Angel Portillo Lucas',
            'email' => 'angelportillo@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Angel',
                'sn1' => 'Portillo',
                'sn2' => 'Lucas',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('606')->id,
                    'family_id' => Family::findByCode('ELECTRIC')->id,
                    'code' => '037',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '037',
                'department_id' => Department::findByCode('ELÈCTRICS')->id
            ]));
    }
}

if (!function_exists('initialize_teachers_sanitat')) {
    function initialize_teachers_sanitat()
    {
        User::createIfNotExists([
            'name' => 'Anna Valls Montagut',
            'email' => 'avalls@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Anna',
                'sn1' => 'Valls',
                'sn2' => 'Montagut',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('517')->id,
                    'family_id' => Family::findByCode('SANITAT')->id,
                    'code' => '064',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '064',
                'department_id' => Department::findByCode('SANITAT')->id
            ]));

        User::createIfNotExists([
            'name' => 'Anna Benaiges Bertomeu',
            'email' => 'anabenaiges@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Anna',
                'sn1' => 'Benaiges',
                'sn2' => 'Bertomeu',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('517')->id,
                    'family_id' => Family::findByCode('SANITAT')->id,
                    'code' => '065',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '065',
                'department_id' => Department::findByCode('SANITAT')->id
            ]));

        User::createIfNotExists([
            'name' => 'Salomé Figueres Brescolí',
            'email' => 'salomefigueres@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Salomé',
                'sn1' => 'Figueres',
                'sn2' => 'Brescolí',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('517')->id,
                    'family_id' => Family::findByCode('SANITAT')->id,
                    'code' => '067',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '067',
                'department_id' => Department::findByCode('SANITAT')->id
            ]));

        User::createIfNotExists([
            'name' => 'Pepa Cugat Tomàs',
            'email' => 'pepacugat@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Pepa',
                'sn1' => 'Cugat',
                'sn2' => 'Tomàs',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('517')->id,
                    'family_id' => Family::findByCode('SANITAT')->id,
                    'code' => '066',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '066',
                'department_id' => Department::findByCode('SANITAT')->id
            ]));

        User::createIfNotExists([
            'name' => 'Berta Safont Recatalà',
            'email' => 'bertasafont@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Berta',
                'sn1' => 'Safont',
                'sn2' => 'Recatalà',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('518')->id,
                    'family_id' => Family::findByCode('SANITAT')->id,
                    'code' => '062',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '062',
                'department_id' => Department::findByCode('SANITAT')->id
            ]));

        User::createIfNotExists([
            'name' => 'MªJesús Sales Berire',
            'email' => 'msales@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Mª Jesús',
                'sn1' => 'Sales',
                'sn2' => 'Berire',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('518')->id,
                    'family_id' => Family::findByCode('SANITAT')->id,
                    'code' => '060',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '060',
                'department_id' => Department::findByCode('SANITAT')->id
            ]));

        User::createIfNotExists([
            'name' => 'MªLuisa Asensi Moltalva',
            'email' => 'mariaasensi@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Mª Jesús',
                'sn1' => 'Asensi',
                'sn2' => 'Moltalva',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('518')->id,
                    'family_id' => Family::findByCode('SANITAT')->id,
                    'code' => '061',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '061',
                'department_id' => Department::findByCode('SANITAT')->id
            ]));

        User::createIfNotExists([
            'name' => 'Santi López Garcia',
            'email' => 'santiagolopez@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Santi',
                'sn1' => 'López',
                'sn2' => 'Garcia',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('518')->id,
                    'family_id' => Family::findByCode('SANITAT')->id,
                    'code' => '063',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '063',
                'department_id' => Department::findByCode('SANITAT')->id
            ]));

        User::createIfNotExists([
            'name' => 'Lluis Ventura Forner',
            'email' => 'lventura@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Lluis',
                'sn1' => 'Ventura',
                'sn2' => 'Forner',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('619')->id,
                    'family_id' => Family::findByCode('SANITAT')->id,
                    'code' => '069',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '069',
                'department_id' => Department::findByCode('SANITAT')->id
            ]));

        User::createIfNotExists([
            'name' => 'J.Antoni Pons Albalat',
            'email' => 'jpons@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'J.Antoni',
                'sn1' => 'Pons',
                'sn2' => 'Albalat',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('619')->id,
                    'family_id' => Family::findByCode('SANITAT')->id,
                    'code' => '070',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '070',
                'department_id' => Department::findByCode('SANITAT')->id
            ]));

        User::createIfNotExists([
            'name' => 'Alicia Fàbrega Martínez',
            'email' => 'aliciafabrega@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Alicia',
                'sn1' => 'Fàbrega',
                'sn2' => 'Martínez',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('619')->id,
                    'family_id' => Family::findByCode('SANITAT')->id,
                    'code' => '071',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '071',
                'department_id' => Department::findByCode('SANITAT')->id
            ]));

        User::createIfNotExists([
            'name' => 'Segis Benabent Gil',
            'email' => 'sbenabent@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Segis',
                'sn1' => 'Benabent',
                'sn2' => 'Gil',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('619')->id,
                    'family_id' => Family::findByCode('SANITAT')->id,
                    'code' => '072',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '072',
                'department_id' => Department::findByCode('SANITAT')->id
            ]));

        User::createIfNotExists([
            'name' => 'Sandra Salvador Jovaní',
            'email' => 'sandrasalvador@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Sandra',
                'sn1' => 'Salvador',
                'sn2' => 'Jovaní',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('619')->id,
                    'family_id' => Family::findByCode('SANITAT')->id,
                    'code' => '068',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '068',
                'department_id' => Department::findByCode('SANITAT')->id
            ]));

        User::createIfNotExists([
            'name' => 'MªJosé Caballé Valverde',
            'email' => 'mcaballe@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'MªJosé',
                'sn1' => 'Caballé',
                'sn2' => 'Valverde',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('620')->id,
                    'family_id' => Family::findByCode('SANITAT')->id,
                    'code' => '074',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '074',
                'department_id' => Department::findByCode('SANITAT')->id
            ]));

        User::createIfNotExists([
            'name' => 'Marisa Ramón Pérez',
            'email' => 'mramon@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Marisa',
                'sn1' => 'Ramón',
                'sn2' => 'Pérez',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('620')->id,
                    'family_id' => Family::findByCode('SANITAT')->id,
                    'code' => '073',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '073',
                'department_id' => Department::findByCode('SANITAT')->id
            ]));

        User::createIfNotExists([
            'name' => 'Elisa Puig Moll',
            'email' => 'epuig@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Elisa',
                'sn1' => 'Puig',
                'sn2' => 'Moll',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('620')->id,
                    'family_id' => Family::findByCode('SANITAT')->id,
                    'code' => '075',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '075',
                'department_id' => Department::findByCode('SANITAT')->id
            ]));

        User::createIfNotExists([
            'name' => 'Ruth Hidalgo Vilar',
            'email' => 'rhidalgo@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Ruth',
                'sn1' => 'Hidalgo',
                'sn2' => 'Vilar',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('620')->id,
                    'family_id' => Family::findByCode('SANITAT')->id,
                    'code' => '076',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '076',
                'department_id' => Department::findByCode('SANITAT')->id
            ]));

        User::createIfNotExists([
            'name' => 'Anna Sambartolomé Sancho',
            'email' => 'annasambartolome@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Anna',
                'sn1' => 'Sambartolomé',
                'sn2' => 'Sancho',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('620')->id,
                    'family_id' => Family::findByCode('SANITAT')->id,
                    'code' => '077',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '077',
                'department_id' => Department::findByCode('SANITAT')->id
            ]));

        User::createIfNotExists([
            'name' => 'Cinta Mestre Escorihuela',
            'email' => 'cintamestre@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Cinta',
                'sn1' => 'Mestre',
                'sn2' => 'Escorihuela',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('620')->id,
                    'family_id' => Family::findByCode('SANITAT')->id,
                    'code' => '078',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '078',
                'department_id' => Department::findByCode('SANITAT')->id
            ]));

        User::createIfNotExists([
            'name' => 'Fabiola Grau Talens',
            'email' => 'fgrau@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Fabiola',
                'sn1' => 'Grau',
                'sn2' => 'Talens',
            ])->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('620')->id,
                    'family_id' => Family::findByCode('SANITAT')->id,
                    'code' => '079',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '079',
                'department_id' => Department::findByCode('SANITAT')->id
            ]));

        User::createIfNotExists([
            'name' => 'Trini Tomas Forcadell',
            'email' => 'trinidadtomas@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Trini',
                'sn1' => 'Tomas',
                'sn2' => 'Forcadell',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('620')->id,
                    'family_id' => Family::findByCode('SANITAT')->id,
                    'code' => '080',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '080',
                'department_id' => Department::findByCode('SANITAT')->id
            ]));

        User::createIfNotExists([
            'name' => 'Adonay Pérez López',
            'email' => 'aperez@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Adonay',
                'sn1' => 'Pérez',
                'sn2' => 'López',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('620')->id,
                    'family_id' => Family::findByCode('SANITAT')->id,
                    'code' => '081',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '081',
                'department_id' => Department::findByCode('SANITAT')->id
            ]));

        User::createIfNotExists([
            'name' => 'Tarsi Royo Cruselles',
            'email' => 'troyo@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Tarsi',
                'sn1' => 'Royo',
                'sn2' => 'Cruselles',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('508')->id,
                    'family_id' => Family::findByCode('SERVEIS')->id,
                    'code' => '082',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '082',
                'department_id' => Department::findByCode('SANITAT')->id
            ]));

        User::createIfNotExists([
            'name' => 'MªCarmen Lorenzo Monfó',
            'email' => 'carmelorenzo@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'MªCarmen',
                'sn1' => 'Lorenzo',
                'sn2' => 'Monfó',
            ])->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('508')->id,
                    'family_id' => Family::findByCode('SERVEIS')->id,
                    'code' => '082',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '083',
                'department_id' => Department::findByCode('SANITAT')->id
            ]));

        User::createIfNotExists([
            'name' => 'Iris Maturana Andreu',
            'email' => 'irismaturana@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Iris',
                'sn1' => 'Maturana',
                'sn2' => 'Andreu',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('508')->id,
                    'family_id' => Family::findByCode('SERVEIS')->id,
                    'code' => '084',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '084',
                'department_id' => Department::findByCode('SANITAT')->id
            ]));
    }
}

if (!function_exists('initialize_teachers_serveis')) {
    function initialize_teachers_serveis()
    {
        User::createIfNotExists([
            'name' => 'Llatzer Carbó Bertomeu',
            'email' => 'llatzercarbo@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Llatzer',
                'sn1' => 'Carbó',
                'sn2' => 'Bertomeu',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('508')->id,
                    'family_id' => Family::findByCode('SERVEIS')->id,
                    'code' => '085',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '085',
                'department_id' => Department::findByCode('SERVEIS')->id
            ]));

        User::createIfNotExists([
            'name' => 'Mercè Gilo Ortiz',
            'email' => 'mercegilo@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Mercè',
                'sn1' => 'Gilo',
                'sn2' => 'Ortiz',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('508')->id,
                    'family_id' => Family::findByCode('SERVEIS')->id,
                    'code' => '086',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '086',
                'department_id' => Department::findByCode('SERVEIS')->id
            ]));

        User::createIfNotExists([
            'name' => 'Cristina Cardona Romero',
            'email' => 'ccardona99@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Cristina',
                'sn1' => 'Cardona',
                'sn2' => 'Romero',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('625')->id,
                    'family_id' => Family::findByCode('SERVEIS')->id,
                    'code' => '087',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '087',
                'department_id' => Department::findByCode('SERVEIS')->id
            ]));

        User::createIfNotExists([
            'name' => 'David Gàmez Balaguer',
            'email' => 'dgamez1@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'David',
                'sn1' => 'Gàmez',
                'sn2' => 'Balaguer',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('625')->id,
                    'family_id' => Family::findByCode('SERVEIS')->id,
                    'code' => '088',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '088',
                'department_id' => Department::findByCode('SERVEIS')->id
            ]));

        User::createIfNotExists([
            'name' => 'Àngels Garrido Borja',
            'email' => 'mgarrido2@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Àngels',
                'sn1' => 'Garrido',
                'sn2' => 'Borja',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('625')->id,
                    'family_id' => Family::findByCode('SERVEIS')->id,
                    'code' => '089',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '089',
                'department_id' => Department::findByCode('SERVEIS')->id
            ]));

        User::createIfNotExists([
            'name' => 'Alicia Gamundi Vilà',
            'email' => 'aliciagamundi@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Alicia',
                'sn1' => 'Gamundi',
                'sn2' => 'Vilà',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('625')->id,
                    'family_id' => Family::findByCode('SERVEIS')->id,
                    'code' => '090',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '090',
                'department_id' => Department::findByCode('SERVEIS')->id
            ]));

        User::createIfNotExists([
            'name' => 'Ricard Gonzalez Castelló',
            'email' => 'rgonzalez1@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Ricard',
                'sn1' => 'Gonzalez',
                'sn2' => 'Castelló',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('625')->id,
                    'family_id' => Family::findByCode('SERVEIS')->id,
                    'code' => '091',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '091',
                'department_id' => Department::findByCode('SERVEIS')->id
            ]));

        User::createIfNotExists([
            'name' => 'Elena Mauri Cuenca',
            'email' => 'elenamauri@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Elena',
                'sn1' => 'Mauri',
                'sn2' => 'Cuenca',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('625')->id,
                    'family_id' => Family::findByCode('SERVEIS')->id,
                    'code' => '092',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '092',
                'department_id' => Department::findByCode('SERVEIS')->id
            ]));

        User::createIfNotExists([
            'name' => 'Irene Alegre Chavarria',
            'email' => 'irenealegre@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Irene',
                'sn1' => 'Alegre',
                'sn2' => 'Chavarria',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('625')->id,
                    'family_id' => Family::findByCode('SERVEIS')->id,
                    'code' => '093',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '093',
                'department_id' => Department::findByCode('SERVEIS')->id
            ]));

        User::createIfNotExists([
            'name' => 'Maria Castells Gilabert',
            'email' => 'mariacastells1@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Maria',
                'sn1' => 'Castells',
                'sn2' => 'Gilabert',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('625')->id,
                    'family_id' => Family::findByCode('SERVEIS')->id,
                    'code' => '108',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '108',
                'department_id' => Department::findByCode('SERVEIS')->id
            ]));
    }
}

if (!function_exists('initialize_teachers_administracio')) {
    function initialize_teachers_administracio()
    {
        User::createIfNotExists([
            'name' => 'Oscar Samo Franch',
            'email' => 'oscarsamo@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Oscar',
                'sn1' => 'Samo',
                'sn2' => 'Franch',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('501')->id,
                    'family_id' => Family::findByCode('ADMIN')->id,
                    'code' => '014',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '014',
                'department_id' => Department::findByCode('ADMINISTRACIÓ')->id
            ]));

        User::createIfNotExists([
            'name' => 'Enric Garcia Carcelén',
            'email' => 'egarci@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Enric',
                'sn1' => 'Garcia',
                'sn2' => 'Carcelén',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('501')->id,
                    'family_id' => Family::findByCode('ADMIN')->id,
                    'code' => '015',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '015',
                'department_id' => Department::findByCode('ADMINISTRACIÓ')->id
            ]));

        User::createIfNotExists([
            'name' => 'Eduard Ralda Simó',
            'email' => 'eralda@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Eduard',
                'sn1' => 'Ralda',
                'sn2' => 'Simó',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('501')->id,
                    'family_id' => Family::findByCode('ADMIN')->id,
                    'code' => '016',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '016',
                'department_id' => Department::findByCode('ADMINISTRACIÓ')->id
            ]));

        User::createIfNotExists([
            'name' => 'Pili Nuez Garcia',
            'email' => 'mnuez@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Pili',
                'sn1' => 'Nuez',
                'sn2' => 'Garcia',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('501')->id,
                    'family_id' => Family::findByCode('ADMIN')->id,
                    'code' => '017',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '017',
                'department_id' => Department::findByCode('ADMINISTRACIÓ')->id
            ]));

        User::createIfNotExists([
            'name' => 'MªRosa Ubalde Bellot',
            'email' => 'mariarosaubalde@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'MªRosa',
                'sn1' => 'Ubalde',
                'sn2' => 'Bellot',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('501')->id,
                    'family_id' => Family::findByCode('ADMIN')->id,
                    'code' => '018',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '018',
                'department_id' => Department::findByCode('ADMINISTRACIÓ')->id
            ]));

        User::createIfNotExists([
            'name' => 'Paqui Pinyol Moreso',
            'email' => 'fpinyol@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Paqui',
                'sn1' => 'Pinyol',
                'sn2' => 'Moreso',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('622')->id,
                    'family_id' => Family::findByCode('ADMIN')->id,
                    'code' => '019',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '019',
                'department_id' => Department::findByCode('ADMINISTRACIÓ')->id
            ]));

        User::createIfNotExists([
            'name' => 'Dolors Subirats Fabra',
            'email' => 'dsubirats@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Dolors',
                'sn1' => 'Subirats',
                'sn2' => 'Fabra',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('622')->id,
                    'family_id' => Family::findByCode('ADMIN')->id,
                    'code' => '020',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '020',
                'department_id' => Department::findByCode('ADMINISTRACIÓ')->id
            ]));

        User::createIfNotExists([
            'name' => 'Ferran Sabaté Borras',
            'email' => 'fsabate@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Ferran',
                'sn1' => 'Sabaté',
                'sn2' => 'Borras',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('622')->id,
                    'family_id' => Family::findByCode('ADMIN')->id,
                    'code' => '021',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '021',
                'department_id' => Department::findByCode('ADMINISTRACIÓ')->id
            ]));

        User::createIfNotExists([
            'name' => 'Araceli Esteller Hierro',
            'email' => 'aesteller@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Araceli',
                'sn1' => 'Esteller',
                'sn2' => 'Hierro',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('622')->id,
                    'family_id' => Family::findByCode('ADMIN')->id,
                    'code' => '022',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '022',
                'department_id' => Department::findByCode('ADMINISTRACIÓ')->id
            ]));

        User::createIfNotExists([
            'name' => 'Mavi Santamaria Andreu',
            'email' => 'mavisantamaria@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Mavi',
                'sn1' => 'Santamaria',
                'sn2' => 'Andreu',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('622')->id,
                    'family_id' => Family::findByCode('ADMIN')->id,
                    'code' => '023',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '023',
                'department_id' => Department::findByCode('ADMINISTRACIÓ')->id
            ]));
    }
}

if (!function_exists('initialize_teachers_comerc')) {
    function initialize_teachers_comerc()
    {
        User::createIfNotExists([
            'name' => 'Agustí Moreso Garcia',
            'email' => 'amoreso@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Agustí',
                'sn1' => 'Moreso',
                'sn2' => 'Garcia',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('510')->id,
                    'family_id' => Family::findByCode('COMERÇ')->id,
                    'code' => '024',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '024',
                'department_id' => Department::findByCode('COMERÇ')->id
            ]));

        User::createIfNotExists([
            'name' => 'Carme Vega Guerra',
            'email' => 'cvega@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Carme',
                'sn1' => 'Vega',
                'sn2' => 'Guerra',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('510')->id,
                    'family_id' => Family::findByCode('COMERÇ')->id,
                    'code' => '025',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '025',
                'department_id' => Department::findByCode('COMERÇ')->id
            ]));

        User::createIfNotExists([
            'name' => 'Dolors Ferreres Gasulla',
            'email' => 'dolorsferreres@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Dolors',
                'sn1' => 'Ferreres',
                'sn2' => 'Gasulla',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('510')->id,
                    'family_id' => Family::findByCode('COMERÇ')->id,
                    'code' => '106',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '106',
                'department_id' => Department::findByCode('COMERÇ')->id
            ]));

        User::createIfNotExists([
            'name' => 'Juan Abad Bueno',
            'email' => 'juandediosabad@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Juan',
                'sn1' => 'Abad',
                'sn2' => 'Bueno',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('510')->id,
                    'family_id' => Family::findByCode('COMERÇ')->id,
                    'code' => '107',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '107',
                'department_id' => Department::findByCode('COMERÇ')->id
            ]));

        User::createIfNotExists([
            'name' => 'Just Pérez Santiago',
            'email' => 'justperez@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Just',
                'sn1' => 'Pérez',
                'sn2' => 'Santiago',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('621')->id,
                    'family_id' => Family::findByCode('COMERÇ')->id,
                    'code' => '026',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '026',
                'department_id' => Department::findByCode('COMERÇ')->id
            ]));

        User::createIfNotExists([
            'name' => 'Armand Pons Roda',
            'email' => 'apons@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Armand',
                'sn1' => 'Pons',
                'sn2' => 'Roda',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('621')->id,
                    'family_id' => Family::findByCode('COMERÇ')->id,
                    'code' => '027',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '027',
                'department_id' => Department::findByCode('COMERÇ')->id
            ]));

        User::createIfNotExists([
            'name' => 'Raquel Planell Tolos',
            'email' => 'raquelplanell@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Armand',
                'sn1' => 'Pons',
                'sn2' => 'Roda',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('621')->id,
                    'family_id' => Family::findByCode('COMERÇ')->id,
                    'code' => '105',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '105',
                'department_id' => Department::findByCode('COMERÇ')->id
            ]));
    }
}

if (!function_exists('initialize_teachers_arts')) {
    function initialize_teachers_arts()
    {
        User::createIfNotExists([
            'name' => 'Marta Grau Ferrer',
            'email' => 'martagrau@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Marta',
                'sn1' => 'Grau',
                'sn2' => 'Ferrer',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('522')->id,
                    'family_id' => Family::findByCode('ARTS')->id,
                    'code' => '094',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '094',
                'department_id' => Department::findByCode('ARTS')->id
            ]));

        User::createIfNotExists([
            'name' => 'Gerard Domenech Vendrell',
            'email' => 'gerarddomenech@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Gerard',
                'sn1' => 'Domenech',
                'sn2' => 'Vendrell',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('623')->id,
                    'family_id' => Family::findByCode('ARTS')->id,
                    'code' => '095',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '095',
                'department_id' => Department::findByCode('ARTS')->id
            ]));

        User::createIfNotExists([
            'name' => 'J.Antonio Fernández Herraez',
            'email' => 'joseantoniofernandez1@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'J.Antonio',
                'sn1' => 'Fernández',
                'sn2' => 'Herraez',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('623')->id,
                    'family_id' => Family::findByCode('ARTS')->id,
                    'code' => '096',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '096',
                'department_id' => Department::findByCode('ARTS')->id
            ]));

        User::createIfNotExists([
            'name' => 'Monica Moreno Dionis',
            'email' => 'monicamoreno@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Monica',
                'sn1' => 'Moreno',
                'sn2' => 'Dionis',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('621')->id,
                    'family_id' => Family::findByCode('ARTS')->id,
                    'code' => '097',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '097',
                'department_id' => Department::findByCode('ARTS')->id
            ]));
    }
}

if (!function_exists('initialize_teachers_informatica')) {
    function initialize_teachers_informatica()
    {
        User::createIfNotExists([
            'name' => 'Santi Sabaté Sanz',
            'email' => 'ssabate@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Santi',
                'sn1' => 'Sabaté',
                'sn2' => 'Sanz',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('507')->id,
                    'family_id' => Family::findByCode('INF')->id,
                    'code' => '038',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '038',
                'department_id' => Department::findByCode('INFORMÀTICA')->id
            ]));

        User::createIfNotExists([
            'name' => 'Jordi Varas Aliau',
            'email' => 'jvaras@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Jordi',
                'sn1' => 'Varas',
                'sn2' => 'Aliau',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('507')->id,
                    'family_id' => Family::findByCode('INF')->id,
                    'code' => '039',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '039',
                'department_id' => Department::findByCode('INFORMÀTICA')->id
            ]));

        User::createIfNotExists([
            'name' => 'Sergi Tur Badenas',
            'email' => 'stur@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Sergi',
                'sn1' => 'Tur',
                'sn2' => 'Badenas',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('507')->id,
                    'family_id' => Family::findByCode('INF')->id,
                    'code' => '040',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '040',
                'department_id' => Department::findByCode('INFORMÀTICA')->id
            ]));

        User::createIfNotExists([
            'name' => 'Jaume Ramos Prades',
            'email' => 'jaumeramos@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Jaume',
                'sn1' => 'Ramos',
                'sn2' => 'Prades',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('507')->id,
                    'family_id' => Family::findByCode('INF')->id,
                    'code' => '041',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '041',
                'department_id' => Department::findByCode('INFORMÀTICA')->id
            ]));

        User::createIfNotExists([
            'name' => 'Quique Lorente Fuertes',
            'email' => 'quiquelorente@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Quique',
                'sn1' => 'Lorente',
                'sn2' => 'Fuertes',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('507')->id,
                    'family_id' => Family::findByCode('INF')->id,
                    'code' => '046',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '046',
                'department_id' => Department::findByCode('INFORMÀTICA')->id
            ]));

        User::createIfNotExists([
            'name' => 'A.Gonzal Verge Arnau',
            'email' => 'goncalverge@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'A.Gonzalb',
                'sn1' => 'Verge',
                'sn2' => 'Arnau',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('507')->id,
                    'family_id' => Family::findByCode('INF')->id,
                    'code' => '117',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '117',
                'department_id' => Department::findByCode('INFORMÀTICA')->id
            ]));

        User::createIfNotExists([
            'name' => 'Mireia Consarnau Pallarés',
            'email' => 'mireiaconsarnau@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Mireia',
                'sn1' => 'Consarnau',
                'sn2' => 'Pallarés',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('627')->id,
                    'family_id' => Family::findByCode('INF')->id,
                    'code' => '042',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '042',
                'department_id' => Department::findByCode('INFORMÀTICA')->id
            ]));

        User::createIfNotExists([
            'name' => 'Manel Macías Valanzuela',
            'email' => 'manelmacias@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Manel',
                'sn1' => 'Macías',
                'sn2' => 'Valanzuela',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('627')->id,
                    'family_id' => Family::findByCode('INF')->id,
                    'code' => '043',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '043',
                'department_id' => Department::findByCode('INFORMÀTICA')->id
            ]));

        User::createIfNotExists([
            'name' => 'Luís Pérez Càrcel',
            'email' => 'luisperez@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Luís',
                'sn1' => 'Pérez',
                'sn2' => 'Càrcel',
            ])->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('627')->id,
                    'family_id' => Family::findByCode('INF')->id,
                    'code' => '044',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '044',
                'department_id' => Department::findByCode('INFORMÀTICA')->id
            ]));

        User::createIfNotExists([
            'name' => 'Josep Diego Cervellera Forcadell',
            'email' => 'josediegocervellera@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Josep Diego',
                'sn1' => 'Cervellera',
                'sn2' => 'Forcadell',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('627')->id,
                    'family_id' => Family::findByCode('INF')->id,
                    'code' => '045',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '045',
                'department_id' => Department::findByCode('INFORMÀTICA')->id
            ]));
    }
}

if (!function_exists('initialize_teachers_mecanica')) {
    function initialize_teachers_mecanica()
    {
        User::createIfNotExists([
            'name' => 'J.Luis Calderon Furió',
            'email' => 'jcaldero@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'J.Luis',
                'sn1' => 'Calderon',
                'sn2' => 'Furió',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('512')->id,
                    'family_id' => Family::findByCode('FABRIC')->id,
                    'code' => '051',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '051',
                'department_id' => Department::findByCode('MECÀNICA')->id
            ]));

        User::createIfNotExists([
            'name' => 'Salvador Jareño Gas',
            'email' => 'sjareno@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Salvador',
                'sn1' => 'Jareño',
                'sn2' => 'Gas',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('512')->id,
                    'family_id' => Family::findByCode('FABRIC')->id,
                    'code' => '052',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '052',
                'department_id' => Department::findByCode('MECÀNICA')->id
            ]));

        User::createIfNotExists([
            'name' => 'Jordi Brau Marza',
            'email' => 'jordibrau@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Jordi',
                'sn1' => 'Brau',
                'sn2' => 'Marza',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('512')->id,
                    'family_id' => Family::findByCode('FABRIC')->id,
                    'code' => '053',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '053',
                'department_id' => Department::findByCode('MECÀNICA')->id
            ]));

        User::createIfNotExists([
            'name' => 'Joan Tiron Ferré',
            'email' => 'jtiron@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Joan',
                'sn1' => 'Tiron',
                'sn2' => 'Ferré',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('611')->id,
                    'family_id' => Family::findByCode('FABRIC')->id,
                    'code' => '054',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '054',
                'department_id' => Department::findByCode('MECÀNICA')->id
            ]));

        User::createIfNotExists([
            'name' => 'Ricard Fernandez Burato',
            'email' => 'rfernand@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Ricard',
                'sn1' => 'Fernandez',
                'sn2' => 'Burato',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('611')->id,
                    'family_id' => Family::findByCode('FABRIC')->id,
                    'code' => '055',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '055',
                'department_id' => Department::findByCode('MECÀNICA')->id
            ]));

        User::createIfNotExists([
            'name' => 'Ubaldo Arroyo Martínez',
            'email' => 'ubaldoarroyo@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Ubaldo',
                'sn1' => 'Arroyo',
                'sn2' => 'Martínez',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('611')->id,
                    'family_id' => Family::findByCode('FABRIC')->id,
                    'code' => '056',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '056',
                'department_id' => Department::findByCode('MECÀNICA')->id
            ]));

        User::createIfNotExists([
            'name' => 'Fernando Segura Venezia',
            'email' => 'fernandosegura@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Fernando',
                'sn1' => 'Segura',
                'sn2' => 'Venezia',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('611')->id,
                    'family_id' => Family::findByCode('FABRIC')->id,
                    'code' => '057',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '057',
                'department_id' => Department::findByCode('MECÀNICA')->id
            ]));

        User::createIfNotExists([
            'name' => 'Francesc Besalduch Piñol',
            'email' => 'sbesalduch@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Francesc',
                'sn1' => 'Besalduch',
                'sn2' => 'Piñol',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('611')->id,
                    'family_id' => Family::findByCode('FABRIC')->id,
                    'code' => '058',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '058',
                'department_id' => Department::findByCode('MECÀNICA')->id
            ]));

        User::createIfNotExists([
            'name' => 'Manel Segarra Capera',
            'email' => 'msegarra@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Manel',
                'sn1' => 'Segarra',
                'sn2' => 'Capera',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('611')->id,
                    'family_id' => Family::findByCode('FABRIC')->id,
                    'code' => '059',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '059',
                'department_id' => Department::findByCode('MECÀNICA')->id
            ]));

        User::createIfNotExists([
            'name' => 'Rosendo Ferri Marzo',
            'email' => 'rosendoferri@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Rosendo',
                'sn1' => 'Ferri',
                'sn2' => 'Marzo',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('611')->id,
                    'family_id' => Family::findByCode('FABRIC')->id,
                    'code' => '049',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '049',
                'department_id' => Department::findByCode('MECÀNICA')->id
            ]));

        User::createIfNotExists([
            'name' => 'Jordi Sanchez Bel',
            'email' => 'jordisanchez@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Jordi',
                'sn1' => 'Sanchez',
                'sn2' => 'Bel',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('611')->id,
                    'family_id' => Family::findByCode('FABRIC')->id,
                    'code' => '050',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '050',
                'department_id' => Department::findByCode('MECÀNICA')->id
            ]));

        User::createIfNotExists([
            'name' => 'Albert Rofí Estelles',
            'email' => 'arofin@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Albert',
                'sn1' => 'Rofí',
                'sn2' => 'Estelles',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('504')->id,
                    'family_id' => Family::findByCode('EDIFIC')->id,
                    'code' => '047',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '047',
                'department_id' => Department::findByCode('MECÀNICA')->id
            ]));

        User::createIfNotExists([
            'name' => 'Pedro Guerrero López',
            'email' => 'pedroguerrero@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Pedro',
                'sn1' => 'Guerrero',
                'sn2' => 'López',
            ])
            ->assignJob(
                Job::firstOrCreate([
                    'type_id' => JobType::findByName('Professor/a')->id,
                    'specialty_id' => Specialty::findByCode('612')->id,
                    'family_id' => Family::findByCode('EDIFIC')->id,
                    'code' => '048',
                    'order' => 1
                ])
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => '048',
                'department_id' => Department::findByCode('MECÀNICA')->id
            ]));
    }
}

if (!function_exists('initialize_substitutes')) {
    function initialize_substitutes()
    {
        // Plaça 004 Dos substituts
        // Núria Vallés Machirant (nº4) Anglès -> S04 Silvia Armengol Bosch (47829022Q)

        // Patrícia Prado Villegas (nº 110) esp. 518 (Dep. Sanitat)

        // Plaça 114 -> No sembla que substituexi a ningú! Nova plaça de centre?
        // Lluc Ulldemolins Nolla (nº 114) esp. 525 (Dep. Electricitat....)
        // Plaça 115 -> No sembla que substituexi a ningú! Nova plaça de centre?
        // Carlos Montesó Esmel (nº115) esp. 524 (Dep. Electricitat...)

        // Dades que falten i surten a Ebre-escool:
        // Núria Sayas Espuny (s24) 52601105J va substituir Agustí Moreso? profe 24
        // Núria Segura Capera (s36) 18967997H -> Francesc Audi Povedano -> Jubilació Colomé

        // Javier (Xavi) Sancho Fabregat substitut de profe 41 Jaume Ramos 47643281T
        User::createIfNotExists([
            'name' => 'Javier Sancho Fabregat',
            'email' => 'javiersancho@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Javier',
                'sn1' => 'Sancho',
                'sn2' => 'Fabregat',
            ])
            ->assignJob(
                Job::findByCode('041'), // Subtitut de Jaume Ramos
                false,
                Carbon::parse('2018-03-30'),
                Carbon::parse('2018-06-30')
            )->assignTeacher(Teacher::firstOrCreate([
                'code' => Teacher::firstAvailableCode(),
                'department_id' => Department::findByCode('INFORMÀTICA')->id
            ]));

        // S47 Josep Vilanova Roig Substitut de Peré Ferre 47626309W

        // S55 Albert Tiron -> Ricard Fernandez Burato 47476671W

        // S66 Laura Vidal Sancho -> Substituta de Pepa Cugat 4782673B

        // S72 Felipe Perez Viana 73563814Q

        // S79 Patrícia García Saez 21677820K

        // S83 Isabel Jovani Castillo 20470918K

        // S93 Vicente Martínez Aznar 19000244J


        // Mª José Dominguez Rodríguez (nº101) esp. 620 (Dep. Sanitat)
        // Núria Suñé Alañá (nº 109) esp. 620 (Dep. Sanitat)
    }
}

if (!function_exists('initialize_teachers')) {
    function initialize_teachers()
    {
        initialize_teachers_ppas();
        initialize_teachers_fol();
        initialize_teachers_administracio();
        initialize_teachers_comerc();
        initialize_teachers_electrics();
        initialize_teachers_informatica();
        initialize_teachers_mecanica();
        initialize_teachers_sanitat();
        initialize_teachers_serveis();
        initialize_teachers_arts();

        // TODO: Professors substituts falta alguna informació!

        // És la substituta que farà 0,33 jornada de Pepa Cugat TODO 0,33!!!!!!!!!!!!!!!!!
        User::createIfNotExists([
            'name' => 'Marta Delgado Escura',
            'email' => 'martadelgado@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Marta',
                'sn1' => 'Delgado',
                'sn2' => 'Escura',
            ])->assignTeacher(Teacher::firstOrCreate([
                'code' => '099'
            ]));

//        Carles Ferré Serret (nº 100). esp .620. Dep.Sanitat. DNI: 40929388Z. Té una jornada de 0,83 i estarà tot el curs.
//    carlesferre78@gmail.com

        User::createIfNotExists([
            'name' => 'Carles Ferré Serret',
            'email' => 'carlesferre@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Carles',
                'sn1' => 'Ferré',
                'sn2' => 'Serret',
            ])->assignTeacher(Teacher::firstOrCreate([
                'code' => '100'
            ]));

        //Mª Piedad Martin Borràs (nº 103) esp. 619. Dep. Sanitat DNI: 52609442R. Jornada de 0,33. Estarà tot el curs.
//    pmartin@coft.org
        User::createIfNotExists([
            'name' => 'MªPiedad Martin Borràs',
            'email' => 'mariapiedadmartin@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'MªPiedad',
                'sn1' => 'Martin',
                'sn2' => 'Borràs',
            ])->assignTeacher(Teacher::firstOrCreate([
                'code' => '103'
            ]));

        User::createIfNotExists([
            'name' => 'María José Domínguez Rodríguez',
            'email' => 'mariajosedominguez@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'María José',
                'sn1' => 'Domínguez',
                'sn2' => 'Rodríguez',
            ])->assignTeacher(Teacher::firstOrCreate([
                'code' => '101'
            ]));

        User::createIfNotExists([
            'name' => 'Eva Benet Escoda',
            'email' => 'evabenet@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Eva',
                'sn1' => 'Benet',
                'sn2' => 'Escoda',
            ])->assignTeacher(Teacher::firstOrCreate([
                'code' => '102'
            ]));

        User::createIfNotExists([
            'name' => 'Miguel Bardi Alegre',
            'email' => 'miguelbardi@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Miguel',
                'sn1' => 'Bardi',
                'sn2' => 'Alegre',
            ])->assignTeacher(Teacher::firstOrCreate([
                'code' => '111'
            ]));


        User::createIfNotExists([
            'name' => 'Mercè Ferré Aixalà',
            'email' => 'merceferre1@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Mercè',
                'sn1' => 'Ferré',
                'sn2' => 'Aixalà',
            ])->assignTeacher(Teacher::firstOrCreate([
                'code' => '112'
            ]));


        User::createIfNotExists([
            'name' => 'Carlos Montesó Esmel',
            'email' => 'carlosmonteso@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Carlos',
                'sn1' => 'Montesó',
                'sn2' => 'Esmel',
            ])->assignTeacher(Teacher::firstOrCreate([
                'code' => '115'
            ]));

        User::createIfNotExists([
            'name' => 'Patricia Prado Villegas',
            'email' => 'patriciaprado@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Patricia',
                'sn1' => 'Prado',
                'sn2' => 'Villegas',
            ])->assignTeacher(Teacher::firstOrCreate([
                'code' => '110'
            ]));

        User::createIfNotExists([
            'name' => 'Ivan Roche Jodar',
            'email' => 'ivanroche@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Ivan',
                'sn1' => 'Roche',
                'sn2' => 'Jodar',
            ])->assignTeacher(Teacher::firstOrCreate([
                'code' => '118',
                'department_id' => Department::findByCode('INFORMÀTICA')->id
            ]));

        User::createIfNotExists([
            'name' => 'Eduard Serra Pons',
            'email' => 'eduardserra@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Eduard',
                'sn1' => 'Serra',
                'sn2' => 'Pons',
            ])->assignTeacher(Teacher::firstOrCreate([
                'code' => '098'
            ]));

        User::createIfNotExists([
            'name' => 'Núria Suñé Alaña',
            'email' => 'eduardserra@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Núria',
                'sn1' => 'Suñé',
                'sn2' => 'Alaña',
            ])->assignTeacher(Teacher::firstOrCreate([
                'code' => '109'
            ]));

        User::createIfNotExists([
            'name' => 'Lluc Ulldemolins Nolla',
            'email' => 'lluculldemolins@iesebre.com',
            'password' => 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4', // secret
            'remember_token' => str_random(10),
        ])->addRole(Role::findByName('Teacher'))
            ->assignFullName([
                'givenName' => 'Lluc',
                'sn1' => 'Ulldemolins',
                'sn2' => 'Nolla',
            ])->assignTeacher(Teacher::firstOrCreate([
                'code' => '114'
            ]));
    }
}

if (!function_exists('initialize_families')) {
    function initialize_families()
    {
        // http://queestudiar.gencat.cat/ca/estudis/pfi/cicles/

        Family::firstOrCreate([
            'name' => 'Sanitat',
            'code' => 'SANITAT'
        ]);

        Family::firstOrCreate([
            'name' => 'Informàtica',
            'code' => 'INF'
        ]);

        Family::firstOrCreate([
            'name' => 'Serveis socioculturals i a la comunitat',
            'code' => 'SERVEIS'
        ]);

        Family::firstOrCreate([
            'name' => 'Arts gràfiques',
            'code' => 'ARTS'
        ]);


        Family::firstOrCreate([
            'name' => 'Administració i gestió',
            'code' => 'ADMIN'
        ]);

        Family::firstOrCreate([
            'name' => 'Comerç i marqueting',
            'code' => 'COMERÇ'
        ]);

        Family::firstOrCreate([
            'name' => 'Electricitat i electrònica/ Energia i aigua',
            'code' => 'ELECTRIC'
        ]);

        Family::firstOrCreate([
            'name' => 'Energia i aigua',
            'code' => 'ENERGIA'
        ]);

        Family::firstOrCreate([
            'name' => 'Fabricació mecànica',
            'code' => 'FABRIC'
        ]);

        Family::firstOrCreate([
            'name' => 'Instal·lació i manteniment',
            'code' => 'MANTENIMENT'
        ]);

        // Edificació
        // Ja no tenim estudis actius d'aquesta familia
        // Si que hi ha professors amb l'especialitat -> no podem marcar com softdeleted! Simplement no hi ha cap estudi
        // assignat a aquesta familia
        // També es maté per raons històriques
        Family::firstOrCreate([
            'name' => 'Edificació i obra civil',
            'code' => 'EDIFIC'
        ]);

        // IMPORTANT: PPAS, LLENGUES, FOL, ETC no són families, de fet són transversals a totes les families
        // No descomentar
//        Family::firstOrCreate([
//            'name' => 'Cursos d’accés',
//            'code' => 'CA'
//        ]);
//
//        Family::firstOrCreate([
//            'name' => 'Departament de llengües estrangeres',
//            'code' => 'ESTRANGER'
//        ]);
//
//        Family::firstOrCreate([
//            'name' => 'FOL',
//            'code' => 'FOL'
//        ]);

    }
}

if (!function_exists('initialize_forces')) {
    function initialize_forces()
    {
        Force::firstOrCreate([
            'name' => 'Mestres',
            'code' => 'MESTRES'
        ]);
        Force::firstOrCreate([
            'name' => "Professors d'ensenyament secundari",
            'code' => 'SECUNDARIA'
        ]);
        Force::firstOrCreate([
            'name' => 'Professors tècnics de formació professional',
            'code' => 'PT'
        ]);
        Force::firstOrCreate([
            'name' => "Professors d'escoles oficials d'idiomes",
            'code' => 'IDIOMES'
        ]);
    }
}


if (!function_exists('initialize_administrative_statuses')) {
    function initialize_administrative_statuses()
    {
        AdministrativeStatus::firstOrCreate([
            'name' => 'Funcionari/a amb plaça definitiva',
            'code' => 'FUNCIONARI DEF'
        ]);

        AdministrativeStatus::firstOrCreate([
            'name' => 'Funcionari/a propietari provisional',
            'code' => 'FUNCIONARI PROV'
        ]);

        AdministrativeStatus::firstOrCreate([
            'name' => 'Funcionari/a en pràctiques',
            'code' => 'FUNCIONARI PRAC'
        ]);

        AdministrativeStatus::firstOrCreate([
            'name' => 'Comissió de serveis',
            'code' => 'COMISSIÓ'
        ]);

        AdministrativeStatus::firstOrCreate([
            'name' => 'Interí/na',
            'code' => 'INTERÍ'
        ]);

        AdministrativeStatus::firstOrCreate([
            'name' => 'Substitut/a',
            'code' => 'SUBSTITUT'
        ]);

        AdministrativeStatus::firstOrCreate([
            'name' => 'Expert/a',
            'code' => 'EXPERT'
        ]);
    }
}

if (!function_exists('initialize_positions')) {
    function initialize_positions()
    {
        Position::firstOrCreate([
            'name' => 'Coordinador TIC/TAC',
        ]);

        Position::firstOrCreate([
            'name' => 'Director',
        ]);

        // TODO
    }
}

if (!function_exists('initialize_specialities')) {
    function initialize_specialities()
    {
        // Sanitat
        Specialty::firstOrCreate([
            'code' => '517',
            'name' => 'Processos diagnòstics clínics i productes ortoprotètics',
            'force_id' => Force::findByCode('SECUNDARIA')->id,
            'family_id' => Family::findByCode('SANITAT')->id
        ]);

        Specialty::firstOrCreate([
            'code' => '518',
            'name' => 'Processos sanitaris',
            'force_id' => Force::findByCode('SECUNDARIA')->id,
            'family_id' => Family::findByCode('SANITAT')->id
        ]);

        // Cos -> Professors tècnics de formació professional
        Specialty::firstOrCreate([
            'code' => '619',
            'name' => 'Procediments de diagnòstic clínic i productes ortoprotètics',
            'force_id' => Force::findByCode('PT')->id,
            'family_id' => Family::findByCode('SANITAT')->id
        ]);

        // Cos -> Professors tècnics de formació professional
        Specialty::firstOrCreate([
            'code' => '620',
            'name' => 'Procediments sanitaris i assistencials ',
            'force_id' => Force::findByCode('PT')->id,
            'family_id' => Family::findByCode('SANITAT')->id
        ]);

        // Serveis socioculturals i a la comunitat
        Specialty::firstOrCreate([
            'code' => '508',
            'name' => 'Intervenció sociocomunitària',
            'force_id' => Force::findByCode('SECUNDARIA')->id,
            'family_id' => Family::findByCode('SERVEIS')->id
        ]);

        Specialty::firstOrCreate([
            'code' => '625',
            'name' => 'Serveis a la comunitat',
            'force_id' => Force::findByCode('PT')->id,
            'family_id' => Family::findByCode('SERVEIS')->id
        ]);

        // Administració i finances
        Specialty::firstOrCreate([
            'code' => '501',
            'name' => 'Administració d’Empreses',
            'force_id' => Force::findByCode('SECUNDARIA')->id,
            'family_id' => Family::findByCode('ADMIN')->id
        ]);

        Specialty::firstOrCreate([
            'code' => '622',
            'name' => 'Processos de Gestió Administrativa',
            'force_id' => Force::findByCode('PT')->id,
            'family_id' => Family::findByCode('ADMIN')->id
        ]);

        // Comerç i marqueting
        Specialty::firstOrCreate([
            'code' => '510',
            'name' => 'Organització i gestió comercial',
            'force_id' => Force::findByCode('SECUNDARIA')->id,
            'family_id' => Family::findByCode('ADMIN')->id
        ]);

        Specialty::firstOrCreate([
            'code' => '621',
            'name' => 'Processos comercials',
            'force_id' => Force::findByCode('PT')->id,
            'family_id' => Family::findByCode('ADMIN')->id
        ]);

        // Informática
        Specialty::firstOrCreate([
            'code' => '507',
            'name' => 'Informàtica',
            'force_id' => Force::findByCode('SECUNDARIA')->id,
            'family_id' => Family::findByCode('INF')->id
        ]);

        Specialty::firstOrCreate([
            'code' => '627',
            'name' => 'Sistemes i aplicacions informàtiques',
            'force_id' => Force::findByCode('PT')->id,
            'family_id' => Family::findByCode('INF')->id
        ]);

        // Electricitat i electrònica/ Energia i aigua
        Specialty::firstOrCreate([
            'code' => '524',
            'name' => 'Sistemes electrònics',
            'force_id' => Force::findByCode('SECUNDARIA')->id,
            'family_id' => Family::findByCode('ELECTRIC')->id
        ]);

        Specialty::firstOrCreate([
            'code' => '525',
            'name' => 'Sistemes electrònics i automàtics',
            'force_id' => Force::findByCode('SECUNDARIA')->id,
            'family_id' => Family::findByCode('ELECTRIC')->id
        ]);

        Specialty::firstOrCreate([
            'code' => '513',
            'name' => 'Organització i projectes de sistemes energètics',
            'force_id' => Force::findByCode('SECUNDARIA')->id,
            'family_id' => Family::findByCode('ELECTRIC')->id
        ]);

        Specialty::firstOrCreate([
            'code' => '602',
            'name' => 'Equips electrònics',
            'force_id' => Force::findByCode('PT')->id,
            'family_id' => Family::findByCode('ELECTRIC')->id
        ]);

        Specialty::firstOrCreate([
            'code' => '605',
            'name' => 'Instal·lació i manteniment d’equips tèrmics i de fluids',
            'force_id' => Force::findByCode('PT')->id,
            'family_id' => Family::findByCode('ELECTRIC')->id
        ]);

        Specialty::firstOrCreate([
            'code' => '606',
            'name' => 'Instal·lacions electrotècniques',
            'force_id' => Force::findByCode('SECUNDARIA')->id,
            'family_id' => Family::findByCode('ELECTRIC')->id
        ]);

        // Fabricació mecànica/ Instal·lació i manteniment
        Specialty::firstOrCreate([
            'code' => '512',
            'name' => 'Organització i projectes de fabricació mecànica',
            'force_id' => Force::findByCode('SECUNDARIA')->id,
            'family_id' => Family::findByCode('FABRIC')->id
        ]);

        Specialty::firstOrCreate([
            'code' => '611',
            'name' => 'Mecanització i manteniment de màquines',
            'force_id' => Force::findByCode('PT')->id,
            'family_id' => Family::findByCode('FABRIC')->id
        ]);

        // Arts gràfiques
        Specialty::firstOrCreate([
            'code' => '522',
            'name' => "Processos i productes d'arts gràfiques.",
            'force_id' => Force::findByCode('SECUNDARIA')->id,
            'family_id' => Family::findByCode('ARTS')->id
        ]);

        Specialty::firstOrCreate([
            'code' => '623',
            'name' => 'Producció en arts gràfiques',
            'force_id' => Force::findByCode('PT')->id,
            'family_id' => Family::findByCode('ARTS')->id
        ]);


        // Edificació i obra civil
        Specialty::firstOrCreate([
            'code' => '504',
            'name' => 'Construccions civils i edificació',
            'force_id' => Force::findByCode('SECUNDARIA')->id,
            'family_id' => Family::findByCode('EDIFIC')->id
        ]);

        Specialty::firstOrCreate([
            'code' => '612',
            'name' => 'Oficina de projectes de construcció',
            'force_id' => Force::findByCode('PT')->id,
            'family_id' => Family::findByCode('EDIFIC')->id
        ]);

        // Cursos d’accés
        // Cos -> Secundària
        Specialty::firstOrCreate([
            'code' => 'MA',
            'name' => 'Matemàtiques',
            'force_id' => Force::findByCode('SECUNDARIA')->id
        ]);

        Specialty::firstOrCreate([
            'code' => 'CAS',
            'name' => 'Castellà',
            'force_id' => Force::findByCode('SECUNDARIA')->id
        ]);

        // Departament de llengües estrangeres
        // Cos -> Secundària
        Specialty::firstOrCreate([
            'code' => 'AN',
            'name' => 'Anglès',
            'force_id' => Force::findByCode('SECUNDARIA')->id
        ]);

        // FOL
        Specialty::firstOrCreate([
            'code' => '505',
            'name' => 'Formació i orientació laboral',
            'force_id' => Force::findByCode('SECUNDARIA')->id
        ]);

    }
}



if (!function_exists('initialize_user_types')) {
    function initialize_user_types()
    {
        $teacher = UserType::firstOrCreate([
            'name' => 'Professor/a'
        ]);
        $teacher->roles()->save(Role::findByName('Teacher'));

        $student = UserType::firstOrCreate([
            'name' => 'Alumne/a'
        ]);
        $student->roles()->save(Role::findByName('Student'));

        UserType::firstOrCreate([
            'name' => 'Conserge'
        ]);

        UserType::firstOrCreate([
            'name' => 'Administratiu/va'
        ]);

        UserType::firstOrCreate([
            'name' => 'Familiars'
        ]);
    }
}

if (!function_exists('apply_tenant')) {
    function apply_tenant($name)
    {
        if ($tenant = get_tenant($name)) {
            $tenant->connect();
            $tenant->configure();
            Config::set('database.default', 'tenant');
        } else {
            dump('Tenant not found!');
        }
    }
}

if (!function_exists('add_fake_pending_teacher')) {
    function add_fake_pending_teacher()
    {
        initialize_administrative_statuses();
        seed_identifier_types();

        return PendingTeacher::create([
            'name' => 'Pepe',
            'sn1' => 'Pardo',
            'sn2' => 'Jeans',
            'identifier' => '84008343S',
            'identifier_type' => 'NIF',
            'birthdate' => '1980-02-04',
            'street' => 'Alcanyiz',
            'number' => 40,
            'floor' => 3,
            'floor_number' => 1,
            'postal_code' => 43500,
            'locality_id' => 13560,
            'locality' => 'TORTOSA',
            'province_id' => 36,
            'province' => 'TARRAGONA',
            'email' => 'pepe@pardo.com',
            'other_emails' => 'pepepardojeans@gmail.com,ppardo@xtec.cat',
            'phone' => '977405689',
            'other_phones' => '977854265,9778542456',
            'mobile' => '679852467',
            'other_mobiles' => '651750489,689534729',
            'degree' => 'Enginyer en chapuzas varias',
            'other_degrees' => 'Master emerito por la Juan Carlos Primero',
            'languages' => 'Suajili',
            'profiles' => 'Master of the Universe',
            'other_training' => 'Fuster',
            'force_id' => 1,
            'force' => 'Mestres',
            'specialty_id' => 1,
            'specialty' => 'Processos sanitaris',
            'teacher_start_date' => '2015',
            'start_date' => '2017-03-06',
            'opositions_date' => '2009-06-10',
            'administrative_status_id' => AdministrativeStatus::findByName('Interí/na')->id,
            'administrative_status' => 'Interí/na',
            'destination_place' => 'La Seu Urgell',
            'teacher_id' => 8,
            'teacher' => 'Sergi Tur Badenas',
        ]);
    }
}

if (!function_exists('add_fake_pending_teachers')) {
    function add_fake_pending_teachers()
    {
        PendingTeacher::create([
            'name' => 'Pepe',
            'sn1' => 'Pardo',
            'sn2' => 'Jeans',
            'identifier' => '84008343S',
            'birthdate' => '1980-02-04',
            'street' => 'Alcanyiz',
            'number' => 40,
            'floor' => 3,
            'floor_number' => 1,
            'postal_code' => 43500,
            'locality_id' => 13560,
            'locality' => 'TORTOSA',
            'province_id' => 36,
            'province' => 'TARRAGONA',
            'email' => 'pepe@pardo.com',
            'other_emails' => 'pepepardojeans@gmail.com,ppardo@xtec.cat',
            'phone' => '679852467',
            'other_phones' => '977854265,689578458',
            'degree' => 'Enginyer en chapuzas varias',
            'other_degrees' => 'Master emerito por la Juan Carlos Primero',
            'languages' => 'Suajili',
            'profiles' => 'Master of the Universe',
            'other_training' => 'Fuster',
            'force_id' => 1,
            'specialty_id' => 1,
            'teacher_start_date' => '2015',
            'start_date' => '2017-03-06',
            'opositions_date' => '2009-06-10',
            'administrative_status_id' => 1,
            'destination_place' => 'La Seu Urgell',
            'teacher_id' => 8
        ]);

        PendingTeacher::create([
            'name' => 'Pepa',
            'sn1' => 'Parda',
            'sn2' => 'Jeans',
            'identifier' => '69544773H',
            'birthdate' => '1975-04-14',
            'street' => 'Beseit',
            'number' => 14,
            'floor' => 2,
            'floor_number' => 3,
            'postal_code' => 43520,
            'locality' => 'TORTOSA',
            'province' => 'TARRAGONA',
            'email' => 'pepa@parda.com',
            'other_emails' => 'pepapardajeans@gmail.com,pparda@xtec.cat',
            'phone' => '674582467',
            'other_phones' => '977814265,689478458',
            'degree' => 'Enginyera en chapuzas varias',
            'other_degrees' => 'Master a por la Juan Carlos Primero',
            'languages' => 'Suajila',
            'profiles' => 'Mistress of the Universe',
            'other_training' => 'Fustera',
            'force_id' => 1,
            'specialty_id' => 1,
            'teacher_start_date' => '2015',
            'start_date' => '2017-03-06',
            'opositions_date' => '2009-04-11',
            'administrative_status_id' => 5,
            'destination_place' => 'La Roca del Vallés',
            'teacher_id' => 9
        ]);
    }
}

if (! function_exists('seed_states')) {
    function seed_states()
    {

        DB::table('states')->delete();


        // Taken from //https://gist.github.com/daguilarm/0e93b73779f0306e5df2
        DB::table('states')->insert([
            ['id' => '1', 'country_code' => "ESP", 'name' => "Andalucía", 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '2', 'country_code' => "ESP", 'name' => "Aragón", 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '3', 'country_code' => "ESP", 'name' => "Asturias, Principado de", 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '4', 'country_code' => "ESP", 'name' => "Baleares, Islas", 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '5', 'country_code' => "ESP", 'name' => "Canarias", 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '6', 'country_code' => "ESP", 'name' => "Cantabria", 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '7', 'country_code' => "ESP", 'name' => "Castilla y León", 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '8', 'country_code' => "ESP", 'name' => "Castilla - La Mancha", 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '9', 'country_code' => "ESP", 'name' => "Cataluña", 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '10', 'country_code' => "ESP", 'name' => "Comunidad Valenciana", 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '11', 'country_code' => "ESP", 'name' => "Extremadura", 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '12', 'country_code' => "ESP", 'name' => "Galicia", 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '13', 'country_code' => "ESP", 'name' => "Madrid, Comunidad de", 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '14', 'country_code' => "ESP", 'name' => "Murcia, Región de", 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '15', 'country_code' => "ESP", 'name' => "Navarra, Comunidad Foral de", 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '16', 'country_code' => "ESP", 'name' => "País Vasco", 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '17', 'country_code' => "ESP", 'name' => "Rioja, La", 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '18', 'country_code' => "ESP", 'name' => "Ceuta", 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '19', 'country_code' => "ESP", 'name' => "Melilla", 'created_at' => new DateTime, 'updated_at' => new DateTime]
        ]);
    }
}

if (! function_exists('seed_provinces')) {
    function seed_provinces()
    {
        seed_states();

        DB::table('provinces')->delete();


        // Taken from //https://gist.github.com/daguilarm/0e93b73779f0306e5df2
        DB::table('provinces')->insert([
            ['id' => '1','state_id' => 8, 'postal_code_prefix' => '02' , 'name' => 'Albacete', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '2','state_id' => 8, 'postal_code_prefix' => '13' , 'name' => 'Ciudad Real', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '3','state_id' => 8, 'postal_code_prefix' => '16' , 'name' => 'Cuenca', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '4','state_id' => 8, 'postal_code_prefix' => '19' , 'name' => 'Guadalajara', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '5','state_id' => 8, 'postal_code_prefix' => '45' , 'name' => 'Toledo', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '6','state_id' => 2, 'postal_code_prefix' => '22' , 'name' => 'Huesca', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '7','state_id' => 2, 'postal_code_prefix' => '44' , 'name' => 'Teruel', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '8','state_id' => 2, 'postal_code_prefix' => '50' , 'name' => 'Zaragoza', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '9','state_id' => 18, 'postal_code_prefix' => '51' , 'name' => 'Ceuta', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '10','state_id' => 13, 'postal_code_prefix' => '28' , 'name' => 'Madrid', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '11','state_id' => 14, 'postal_code_prefix' => '30' , 'name' => 'Murcia', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '12','state_id' => 19, 'postal_code_prefix' => '52' , 'name' => 'Melilla', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '13','state_id' => 15, 'postal_code_prefix' => '31' , 'name' => 'Navarra', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '14','state_id' => 1, 'postal_code_prefix' => '04' , 'name' => 'Almería', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '15','state_id' => 1, 'postal_code_prefix' => '11' , 'name' => 'Cádiz', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '16','state_id' => 1, 'postal_code_prefix' => '14' , 'name' => 'Córdoba', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '17','state_id' => 1, 'postal_code_prefix' => '18' , 'name' => 'Granada', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '18','state_id' => 1, 'postal_code_prefix' => '21' , 'name' => 'Huelva', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '19','state_id' => 1, 'postal_code_prefix' => '23' , 'name' => 'Jaén', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '20','state_id' => 1, 'postal_code_prefix' => '29' , 'name' => 'Málaga', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '21','state_id' => 1, 'postal_code_prefix' => '41' , 'name' => 'Sevilla', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '22','state_id' => 3, 'postal_code_prefix' => '33' , 'name' => 'Asturias', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '23','state_id' => 6, 'postal_code_prefix' => '39' , 'name' => 'Cantabria', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '24','state_id' => 7, 'postal_code_prefix' => '05' , 'name' => 'Ávila', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '25','state_id' => 7, 'postal_code_prefix' => '09' , 'name' => 'Burgos', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '26','state_id' => 7, 'postal_code_prefix' => '24' , 'name' => 'León', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '27','state_id' => 7, 'postal_code_prefix' => '34' , 'name' => 'Palencia', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '28','state_id' => 7, 'postal_code_prefix' => '37' , 'name' => 'Salamanca', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '29','state_id' => 7, 'postal_code_prefix' => '40' , 'name' => 'Segovia', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '30','state_id' => 7, 'postal_code_prefix' => '42' , 'name' => 'Soria', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '31','state_id' => 7, 'postal_code_prefix' => '47' , 'name' => 'Valladolid', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '32','state_id' => 7, 'postal_code_prefix' => '49' , 'name' => 'Zamora', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '33','state_id' => 9, 'postal_code_prefix' => '08' , 'name' => 'Barcelona', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '34','state_id' => 9, 'postal_code_prefix' => '17' , 'name' => 'Girona', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '35','state_id' => 9, 'postal_code_prefix' => '25' , 'name' => 'Lleida', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '36','state_id' => 9, 'postal_code_prefix' => '43' , 'name' => 'Tarragona', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '37','state_id' => 11, 'postal_code_prefix' => '06' , 'name' => 'Badajoz', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '38','state_id' => 11, 'postal_code_prefix' => '10' , 'name' => 'Cáceres', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '39','state_id' => 12, 'postal_code_prefix' => '15' , 'name' => 'Coruña, La', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '40','state_id' => 12, 'postal_code_prefix' => '27' , 'name' => 'Lugo', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '41','state_id' => 12, 'postal_code_prefix' => '32' , 'name' => 'Orense', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '42','state_id' => 12, 'postal_code_prefix' => '36' , 'name' => 'Pontevedra', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '43','state_id' => 17, 'postal_code_prefix' => '26' , 'name' => 'Rioja, La', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '44','state_id' => 4, 'postal_code_prefix' => '07' , 'name' => 'Baleares, Islas', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '45','state_id' => 16, 'postal_code_prefix' => '01' , 'name' => 'Álava', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '46','state_id' => 16, 'postal_code_prefix' => '20' , 'name' => 'Guipúzcoa', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '47','state_id' => 16, 'postal_code_prefix' => '48' , 'name' => 'Vizcaya', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '48','state_id' => 5, 'postal_code_prefix' => '35' , 'name' => 'Palmas, Las', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '49','state_id' => 5, 'postal_code_prefix' => '38' , 'name' => 'Tenerife, Santa Cruz De', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '50','state_id' => 10, 'postal_code_prefix' => '03' , 'name' => 'Alacant', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '51','state_id' => 10, 'postal_code_prefix' => '12' , 'name' => 'Castelló', 'created_at' => new DateTime, 'updated_at' => new DateTime],
            ['id' => '52','state_id' => 10, 'postal_code_prefix' => '46' , 'name' => 'Valencia', 'created_at' => new DateTime, 'updated_at' => new DateTime]
        ]);
    }
}

if (! function_exists('tune_google_client')) {
    function tune_google_client()
    {
        app()->extend(\PulkitJalan\Google\Client::class, function ($command, $app) {
            $config = $app['config']['google'];
            $user = 'sergitur@iesebre.com';
            return new Client($config, $user);
        });
    }
}

if (! function_exists('get_photo_slugs_from_path')) {
    /**
     * Get photos slugs from path.
     *
     * @param $path
     * @return \Illuminate\Support\Collection|static
     */
    function get_photo_slugs_from_path($path)
    {
        $photos = collect();
        if (Storage::exists($path)) {
            $photos = collect(File::allFiles(Storage::path($path)))->map(function ($photo) {
                return [
                    'file' => $photo,
                    'filename' => $filename = $photo->getFilename(),
                    'slug' => str_slug($filename,'-')
                ];
            });
        }
        return $photos;
    }
}

if (! function_exists('first_or_create_identifier_type')) {
    /**
     * Create contact type if not exists and return new o already existing contact type.
     */
    function first_or_create_identifier_type($name)
    {
        try {
            return IdentifierType::create(['name' => $name]);
        } catch (Illuminate\Database\QueryException $e) {
            return IdentifierType::where('name', $name);
        }
    }
}

if (! function_exists('seed_identifier_types')) {
    /**
     * Create identifier types.
     */
    function seed_identifier_types()
    {
        first_or_create_identifier_type('NIF');
        first_or_create_identifier_type('Pasaporte');
        first_or_create_identifier_type('NIE');
        first_or_create_identifier_type('TIS');
    }
}
if (! function_exists('fake_personal_data_teachers')) {
    function fake_personal_data_teachers() {
        first_or_create_identifier_type('NIF');
        $nif = IdentifierType::findByName('NIF')->id;

        // Note: names are already assigned in initialize_teachers helper
        Teacher::findByCode('041')->user->assignPersonalData([
            'identifier_id' => Identifier::firstOrCreate([
                'value' => '14268002K',
                'type_id' => $nif
            ])->id,
            'birthdate' => Carbon::parse('1978-03-02'),
            'birthplace_id' => Location::findByName('BARCELONA')->id,
            'gender' => 'Home',
            'civil_status' => 'Casat/da',
            'notes' => "Coordinador d'informàtica",
            'mobile' => '679525437',
            'other_mobiles' => '650192821',
            'email' => 'sergiturbadenas@gmail.com',
            'other_emails' => 'acacha@gmail.com,sergitur@iesebre.com',
            'phone' => '977500949',
            'other_phones' => '9677508695,977500949'
        ])->assignAddress(Address::create([
            'name' => 'C/ Beseit',
            'number' => '16',
            'floor' => '4',
            'floor_number' => '2',
            'location_id' => Location::findByName('TORTOSA')->id,
            'province_id' => Province::findByName('TARRAGONA')->id,
        ]))->assignTeacherData([  // Code ius already assigned at initialize_teachers helper
            'administrative_status_id' => AdministrativeStatus::findByName('Substitut/a')->id,
            'specialty_id' => Specialty::findByCode('507')->id,
            'titulacio_acces' => 'Enginyer Superior en Telecomunicacions',
            'altres_titulacions' => 'Postgrau en Programari Lliure',
            'idiomes' => 'Certificat Aptitud Anglès Escola Oficial Idiomes',
            'altres_formacions' => 'Nivell D de Català',
            'data_inici_treball' => '29/09/2006',
            'data_incorporacio_centre' => Carbon::parse('2009-09-01'),
            'data_superacio_oposicions' => 'Juny 2008',
            'perfil_professional' => 'De perfil més guapo sí',
            'lloc_destinacio_definitiva' => 'Al quinto pino!',
        ]);
    }
}

if (! function_exists('autoassign_photos_to_teachers')) {
    function autoassign_photos_to_teachers($path, $tenant)
    {
        $teachersWithoutFoto = Teacher::with(['user'])->get()->filter(function($teacher) {
            if ($teacher->user) return $teacher->user->photo === null;
        });
        $counter= 0;
        $availablephotos = collect(File::allFiles($path));
        foreach ($teachersWithoutFoto as $teacher) {
            $foundPhotos = $availablephotos->filter(function($photo) use ($teacher) {
                return str_contains($photo->getFileName(),$teacher->code);
            });
            if ($foundPhoto = $foundPhotos->first()) {
                $teacher->user->assignPhoto($foundPhoto->getPathname(),$tenant);
                $counter++;
            }
        }

        return $counter;
    }
}

if (!function_exists('initialize_teacher_photos')) {
    function initialize_teacher_photos() {
        $tenant = Config::get('database.connections.tenant.database');
        $src = base_path('storage/photos/' . $tenant . '/teachers/*.*');

        $dest = storage_path('app/' . $tenant . '/teacher_photos');
        shell_exec("cp -r $src $dest");
        autoassign_photos_to_teachers($dest,$tenant);
    }
}

if (!function_exists('initialize_head_departments')) {
    function initialize_head_departments()
    {
        $department = Department::findByCode('PPAS');
        $department->head = User::findByName('Carme Aznar Pedret')->id;
        $department->save();

        $department = Department::findByCode('FOL');
        $department->head = User::findByName('Carmina Andreu Pons')->id;
        $department->save();

        $department = Department::findByCode('ADMINISTRACIÓ');
        $department->head = User::findByName('Eduard Ralda Simó')->id;
        $department->save();

        $department = Department::findByCode('COMERÇ');
        $department->head = User::findByName('Agustí Moreso García')->id;
        $department->save();

        $department = Department::findByCode('ELÈCTRICS');
        $department->head = User::findByName('Xavi Bel Fernandez')->id;
        $department->save();

        $department = Department::findByCode('INFORMÀTICA');
        $department->head = User::findByName('Jordi Varas Aliau')->id;
        $department->save();

        $department = Department::findByCode('MECÀNICA');
        $department->head = User::findByName('Fernando Segura Venezia')->id;
        $department->save();

        $department = Department::findByCode('SANITAT');
        $department->head = User::findByName('Berta Safont Recatalà')->id;
        $department->save();

        $department = Department::findByCode('SERVEIS');
        $department->head = User::findByName('Elena Mauri Cuenca')->id;
        $department->save();

        $department = Department::findByCode('ARTS');
        $department->head = User::findByName('Gerard Domènech Vendrell')->id;
        $department->save();
    }
}

if (!function_exists('initialize_departments')) {
    function initialize_departments()
    {
        Department::firstOrCreate([
            'name' => 'Departament preparació proves d\'accès a superior',
            'shortname' => 'Curs d\'accès | Àngles',
            'code' => 'PPAS',
            'order' => 1
        ]);

        Department::firstOrCreate([
            'name' => 'Departament de formació i orientació laboral',
            'shortname' => 'Formació i orientació laboral',
            'code' => 'FOL',
            'order' => 2
        ]);

        Department::firstOrCreate([
            'name' => 'Departament d\'administració i gestió',
            'shortname' => 'Administració i gestió',
            'code' => 'ADMINISTRACIÓ',
            'order' => 3
        ]);

        Department::firstOrCreate([
            'name' => 'Departament de comerç i màrqueting',
            'shortname' => 'Comerç i màrqueting',
            'code' => 'COMERÇ',
            'order' => 4
        ]);

        Department::firstOrCreate([
            'name' => 'Departament d\'electricitat i electrònica',
            'shortname' => 'Electricitat i electrònica',
            'code' => 'ELÈCTRICS',
            'order' => 5
        ]);

        Department::firstOrCreate([
            'name' => 'Departament d\'informàtica',
            'shortname' => 'Informàtica',
            'code' => 'INFORMÀTICA',
            'order' => 6
        ]);

        Department::firstOrCreate([
            'name' => 'Departament de fabricació mecànica',
            'shortname' => 'Fabricació mecànica',
            'code' => 'MECÀNICA',
            'order' => 7
        ]);

        Department::firstOrCreate([
            'name' => 'Departament de sanitat',
            'shortname' => 'Sanitat',
            'code' => 'SANITAT',
            'order' => 8
        ]);

        Department::firstOrCreate([
            'name' => 'Departament de serveis socioculturals i a la comunitat',
            'shortname' => 'Serveis socioculturals i a la comunitat',
            'code' => 'SERVEIS',
            'order' => 9
        ]);

        Department::firstOrCreate([
            'name' => 'Departament d\'Arts gràfiques',
            'shortname' => 'Arts gràfiques',
            'code' => 'ARTS',
            'order' => 10
        ]);

    }
}

if (!function_exists('name')) {
    /**
     * Name
     *
     * @param $givenName
     * @param $sn1
     * @param string $sn2
     * @return string
     */
    function name($givenName,$sn1, $sn2= '') {
        return trim(trim($givenName) . ' ' . trim($sn1) . ' ' . trim($sn2));
    }
}

if (!function_exists('fullname')) {
    function fullname($givenName, $sn1, $sn2 = '')
    {
        $fullname = trim($sn1);
        if ($sn2) {
            $fullname = $fullname . ' ' . trim($sn2);
        }
        $fullname = $fullname . ', ' . trim($givenName);
        return trim($fullname);
    }
}

if (!function_exists('create_fake_job')) {
    function create_fake_job()
    {
        JobType::create(['name' => 'Professor/a']);
        Force::create([
            'name' => 'Secundària',
            'code' => 'SECUNDARIA'
        ]);

        $family = Family::create([
            'name' => 'Sanitat',
            'code' => 'SANITAT'
        ]);
        Specialty::create([
            'name' => 'Informàtica',
            'code' => '507',
            'force_id' => Force::findByCode('SECUNDARIA'),
            'family_id' => $family->id
        ]);

        return Job::firstOrCreate([
            'code' => '01',
            'type_id' => JobType::findByName('Professor/a')->id,
            'specialty_id' => Specialty::findByCode('507')->id,
            'family_id' => Family::findByCode('SANITAT')->id,
            'order' => 1
        ]);
    }
}

if (!function_exists('create_fake_teacher')) {
    /**
     * Create fake teacher.
     *
     * @return mixed
     */
    function create_fake_teacher()
    {
        $user = (new UserRepository())->store(
            (object) [
                'name' => name('Pepe', 'Pardo', 'Jeans'),
                'email' => 'pepepardo@iesebre.com',
                'photo' => 'user_photos/photo.png'
            ]
        );

        $role = Role::firstOrCreate([
            'name' => 'Teacher',
            'guard_name' => 'web'
        ]);

        $user->addRole($role);

        (new TeacherRepository())->store((object) [
            'user_id' => $user->id,
            'code' => Teacher::firstAvailableCode(),
            'administrative_status_id' => 1,
            'specialty_id' => 1,
            'titulacio_acces' => 'Enginyer',
            'altres_titulacions' => 'Master',
            'idiomes' => 'Anglès',
            'altres_formacions' => 'Nivell D Català',
            'perfil_professional' => 'CLIC',
            'data_inici_treball' => '2009',
            'data_incorporacio_centre' => '2008-02-03',
            'data_superacio_oposicions' => 'juny 2006',
            'lloc_destinacio_definitiva' => 'Quinto Pino'
        ]);

        //Create identifier
        $type = IdentifierType::firstOrCreate([
            'name' => 'NIF'
        ]);
        $identifier = Identifier::firstOrCreate([
            'value' => '54895745N',
            'type_id' => $type->id
        ]);

        $location = Location::firstOrCreate([
            'name' => 'TORTOSA',
            'postalcode' => 43500
        ]);

        $province = Province::firstOrCreate([
            'state_id' => 9,
            'name' => 'Tarragona',
            'postal_code_prefix' => '43'
        ]);

        //Create person
        $person = (new PersonRepository())->store((object) [
            'user_id' => $user->id,
            'identifier_id' => $identifier->id,
            'givenName' => 'Pepe',
            'sn1' => 'Pardo',
            'sn2' => 'Jeans',
            'birthdate' => '2008-02-48',
            'birthplace_id' => $location->id,
            'gender' => 'Home',
            'civil_status' => 'Solter/a',
            'phone' => '977858689',
            'other_phones' => '["977485868","977485969"]',
            'mobile' => '678598458',
            'other_mobiles' => '["645698458","678548558"]',
            'email' => 'myemailpepepardo@gmail.com',
            'other_emails' => '["myemailpepepardo@hotmail.com","myemailpepepardo@yahoo.com"]',
            'notes' => 'Bla bla bla'
        ]);

        //Create address
        Address::create([
            'person_id' => $person->id,
            'name' => 'C/Beseit',
            'number' => '24',
            'floor' => '4t',
            'floor_number' => '2a',
            'location_id' => $location->id,
            'province_id' => $province->id
        ]);

        return $user;
    }
}

if (!function_exists('create_fake_audit_log_entries')) {
    function create_fake_audit_log_entries()
    {
        $job = Job::create([
            'code' => '999',
            'type_id' => 1,
            'specialty_id' => 1,
            'family_id' => 1,
            'order' => 1,
            'notes' => 'Bla bla',
        ]);

        Revision::create([
            'revisionable_type' => Job::class,
            'revisionable_id' => $job->id,
            'user_id' => 1,
            'key' => 'order',
            'old_value' => 1,
            'new_value' => 2
        ]);
    }
}

if (!function_exists('check_audit_log_entry')) {
    function check_audit_log_entry($entry)
    {
        return array_key_exists('id', $entry) &&
            array_key_exists('element', $entry) &&
            array_key_exists('revisionable_type', $entry) &&
            array_key_exists('revisionable_id', $entry) &&
            array_key_exists('type', $entry) &&
            array_key_exists('user', $entry) &&
            array_key_exists('user_id', $entry) &&
            array_key_exists('user_hashid', $entry) &&
            array_key_exists('user_description', $entry) &&
            array_key_exists('key', $entry) &&
            array_key_exists('field_name', $entry) &&
            array_key_exists('old_value', $entry) &&
            array_key_exists('new_value', $entry) &&
            array_key_exists('created_at', $entry) &&
            array_key_exists('formatted_created_at_diff', $entry);

    }
}


if (!function_exists('check_sheet_job')) {
    /**
     * Check sheet job.
     *
     * @param $job
     * @return bool
     */
    function check_sheet_job($job)
    {
        return array_key_exists('id', $job) &&
            array_key_exists('active_user_hash_id', $job) &&
            array_key_exists('active_user_name', $job) &&
            array_key_exists('active_user_email', $job) &&
            array_key_exists('active_user_description', $job);
    }
}

if (!function_exists('check_sheet_job_for_holders')) {
    /**
     * Check sheet job.
     *
     * @param $job
     * @return bool
     */
    function check_sheet_job_for_holders($job)
    {
        return array_key_exists('id', $job) &&
            array_key_exists('code', $job) &&
            array_key_exists('holder_hashid', $job) &&
            array_key_exists('holder_code', $job) &&
            array_key_exists('holder_name', $job) &&
            array_key_exists('holder_email', $job) &&
            array_key_exists('holder_description', $job);
    }
}


if (!function_exists('check_job')) {
    function check_job($job)
    {
        return array_key_exists('id', $job) &&
            array_key_exists('type', $job) &&
            array_key_exists('type_id', $job) &&
            array_key_exists('code', $job) &&
            array_key_exists('holder_id', $job) &&
            array_key_exists('holder_hashid', $job) &&
            array_key_exists('holder_code', $job) &&
            array_key_exists('holder_name', $job) &&
            array_key_exists('holder_description', $job) &&
            array_key_exists('active_user_hash_id', $job) &&
            array_key_exists('active_user_code', $job) &&
            array_key_exists('active_user_name', $job) &&
            array_key_exists('active_user_description', $job) &&
            array_key_exists('substitutes', $job) &&
            array_key_exists('fullcode', $job) &&
            array_key_exists('order', $job) &&
            array_key_exists('family', $job) &&
            array_key_exists('family_id', $job) &&
            array_key_exists('family_code', $job) &&
            array_key_exists('family_description', $job) &&
            array_key_exists('specialty', $job) &&
            array_key_exists('specialty_id', $job) &&
            array_key_exists('specialty_code', $job) &&
            array_key_exists('specialty_description', $job) &&
            array_key_exists('formatted_created_at_diff', $job) &&
            array_key_exists('formatted_created_at', $job) &&
            array_key_exists('formatted_updated_at', $job) &&
            array_key_exists('formatted_updated_at_diff', $job) &&
            array_key_exists('notes', $job);

    }
}

if (!function_exists('check_teacher')) {
    function check_teacher($teacher) {
        return array_key_exists('user_id', $teacher) &&
            array_key_exists('teacher_id', $teacher) &&
            array_key_exists('code', $teacher) &&
            array_key_exists('formatted_created_at_diff', $teacher) &&
            array_key_exists('formatted_created_at', $teacher) &&
            array_key_exists('formatted_updated_at', $teacher) &&
            array_key_exists('formatted_updated_at_diff', $teacher) &&
            array_key_exists('hashid', $teacher) &&
            array_key_exists('name', $teacher) &&
            array_key_exists('email', $teacher) &&
            array_key_exists('fullname', $teacher) &&
            array_key_exists('department_code', $teacher) &&
            array_key_exists('department', $teacher) &&
            array_key_exists('specialty', $teacher) &&
            array_key_exists('specialty_code', $teacher) &&
            array_key_exists('family', $teacher) &&
            array_key_exists('family_code', $teacher) &&
            array_key_exists('force', $teacher) &&
            array_key_exists('administrative_status', $teacher) &&
            array_key_exists('administrative_status_code', $teacher) &&
            array_key_exists('job', $teacher) &&
            array_key_exists('job_description', $teacher) &&
            array_key_exists('job_start_at', $teacher) &&
            array_key_exists('job_end_at', $teacher) &&
            array_key_exists('job_family', $teacher) &&
            array_key_exists('job_specialty', $teacher) &&
            array_key_exists('job_specialty_code', $teacher) &&
            array_key_exists('job_order', $teacher) &&
            array_key_exists('full_search', $teacher) &&
            array_key_exists('titulacio_acces', $teacher) &&
            array_key_exists('altres_titulacions', $teacher) &&
            array_key_exists('idiomes', $teacher) &&
            array_key_exists('perfil_professional', $teacher) &&
            array_key_exists('altres_formacions', $teacher) &&
            array_key_exists('data_superacio_oposicions', $teacher) &&
            array_key_exists('lloc_destinacio_definitiva', $teacher) &&
            array_key_exists('data_inici_treball', $teacher) &&
            array_key_exists('data_incorporacio_centre', $teacher) &&
            array_key_exists('person_notes', $teacher) &&
            array_key_exists('givenName', $teacher) &&
            array_key_exists('sn1', $teacher) &&
            array_key_exists('sn2', $teacher) &&
            array_key_exists('person_notes', $teacher) &&
            array_key_exists('givenName', $teacher) &&
            array_key_exists('sn1', $teacher) &&
            array_key_exists('sn2', $teacher) &&
            array_key_exists('birthdate', $teacher) &&
            array_key_exists('birthplace', $teacher) &&
            array_key_exists('gender', $teacher) &&
            array_key_exists('phone', $teacher) &&
            array_key_exists('other_phones', $teacher) &&
            array_key_exists('mobile', $teacher) &&
            array_key_exists('other_mobiles', $teacher) &&
            array_key_exists('personal_email', $teacher) &&
            array_key_exists('other_emails', $teacher) &&
            array_key_exists('identifier', $teacher) &&
            array_key_exists('identifier_type', $teacher) &&
            array_key_exists('address_name', $teacher) &&
            array_key_exists('address_number', $teacher) &&
            array_key_exists('address_floor', $teacher) &&
            array_key_exists('address_floor_number', $teacher) &&
            array_key_exists('address_location', $teacher) &&
            array_key_exists('address_postalcode', $teacher) &&
            array_key_exists('address_province', $teacher);
    }
}


/**
 * CURRÍCULUM
 */


// SUBJECTS -> Unitat formatives

if (!function_exists('initialize_subjects')) {
    function initialize_subjects()
    {
        $mp_start_date = '2017-09-15';
        $mp_end_date = '2018-06-01';

        //Comú
        $loe = Law::firstOrCreate([
            'code' => 'LOE',
            'name' => 'Ley Orgánica de Educación'
        ]);

        $logse = Law::firstOrCreate([
            'code' => 'LOGSE',
            'name' => 'Ley Orgánica de Ordenació General del Sistema Educativo'
        ]);

        // Per estudis i mòduls:

        // Informàtica
//        Mòdul professional 1: sistemes informàtics

        // http://portaldogc.gencat.cat/utilsEADOP/PDF/6958/1444503.pdf
        // O matrícula TIC

        $study = Study::firstOrCreate([
            'name' => 'Desenvolupament Aplicacions Multiplataforma',
            'code' => 'DAM',
            'law_id' => $loe->id
        ]);

        // No existeix 1DAM -> és comú amb 1rASIX
//        $course1 = Course::create([
//            'name' => 'Desenvolupament Aplicacions Multiplataforma',
//            'code' => '1DAM'
//        ]);
        $course2 = Course::firstOrCreate([
            'code' => '2DAM',
            'name' => 'Desenvolupament Aplicacions Multiplataforma',
            'order' => 2
        ]);

//        Mòdul professional (Subject Group) 7: desenvolupament d’interfícies
        $group = SubjectGroup::firstOrCreate([
            'shortname' => 'Desenvolupament d’interfícies',
            'name' => 'Desenvolupament d’interfícies',
            'code' =>  'DAM_MP7',
            'number' => 7,
            'study_id' => $study->id,
            'hours' => 99,
            'free_hours' => 0, // Lliure disposició
            'week_hours' => 3,
            'start' => $mp_start_date,
            'end' => $mp_end_date,
            'type' => 'Normal'
        ]);
//        $table->enum('type', ['Normal', 'Externes', 'Síntesi', 'FCT' ])->default('Normal');
//        $table->unsignedTinyInteger('order')->nullable();

        Subject::create([
            'name' => 'Disseny i implementació d’interfícies',
            'shortname'=> 'Disseny i implementació d’interfícies',
            'code' =>  'DAM_MP7_UF1',
            'number' => 1,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course2->id,
            'type_id' => 1,
            'hours' => 79,
            'start' => $mp_start_date,
            'end' => $mp_end_date
        ]);

        Subject::create([
            'name' => 'Preparació i distribució d’aplicacions',
            'shortname'=> 'Preparació i distribució d’aplicacions',
            'code' =>  'DAM_MP7_UF2',
            'number' => 2,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course2->id,
            'type_id' => 1,
            'hours' => 20
        ]);

        // Administració
        $study = Study::firstOrCreate([
            'name' => 'Gestió administrativa',
            'code' => 'GAD',
            'law_id' => $loe->id
        ]);

        $course1 = Course::firstOrCreate([
            'code' => '1GAD',
            'name' => 'Gestió administrativa',
            'order' => 1
        ]);

        $course2 = Course::firstOrCreate([
            'code' => '2GAD',
            'name' => 'Gestió administrativa',
            'order' => 2
        ]);

        $group = SubjectGroup::firstOrCreate([
            'shortname' => 'Operacions de compravenda',
            'name' => 'Operacions de compravenda',
            'code' =>  'GAD_MP2',
            'number' => 2,
            'study_id' => $study->id,
            'hours' => 165,
            'free_hours' => 0, // Lliure disposició
            'week_hours' => 0,
            'start' => $mp_start_date,
            'end' => $mp_end_date,
            'type' => 'Normal'
        ]);

        Subject::create([
            'name' => 'Circuit administratiu de la compravenda',
            'shortname'=> 'Circuit administratiu de la compravenda',
            'code' =>  'GAD_MP2_UF1',
            'number' => 1,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 77
        ]);

        Subject::create([
            'name' => "Gestió d'estocs",
            'shortname'=> "Gestió d'estocs",
            'code' =>  'GAD_MP2_UF2',
            'number' => 2,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        Subject::create([
            'name' => "Declaracions fiscals",
            'shortname'=> "Declaracions fiscals",
            'code' =>  'GAD_MP2_UF3',
            'number' => 3,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 55
        ]);

        $group = SubjectGroup::firstOrCreate([
            'shortname' => 'Operacions de recursos humans',
            'name' => 'Operacions de recursos humans',
            'code' =>  'GAD_MP3',
            'number' => 3,
            'study_id' => $study->id,
            'hours' => 99,
            'free_hours' => 0, // Lliure disposició
            'week_hours' => 0,
            'start' => $mp_start_date,
            'end' => $mp_end_date,
            'type' => 'Normal'
        ]);

        Subject::create([
            'name' => "Selecció i formació",
            'shortname'=> "Selecció i formació",
            'code' =>  'GAD_MP3_UF1',
            'number' => 1,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        Subject::create([
            'name' => "Contractació i retribució",
            'shortname'=> "Contractació i retribució",
            'code' =>  'GAD_MP3_UF2',
            'number' => 2,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        Subject::create([
            'name' => "Processos de l’activitat laboral",
            'shortname'=> "Processos de l’activitat laboral",
            'code' =>  'GAD_MP3_UF3',
            'number' => 3,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        $group = SubjectGroup::firstOrCreate([
            'shortname' => 'Operacions de gestió de tresoreria',
            'name' => 'Operacions de gestió de tresoreria',
            'code' =>  'GAD_MP4',
            'number' => 4,
            'study_id' => $study->id,
            'hours' => 132,
            'free_hours' => 0, // Lliure disposició
            'week_hours' => 0,
            'start' => $mp_start_date,
            'end' => $mp_end_date,
            'type' => 'Normal'
        ]);

        Subject::create([
            'name' => "Control de tresoreria",
            'shortname'=> "Control de tresoreria",
            'code' =>  'GAD_MP4_UF1',
            'number' => 1,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        Subject::create([
            'name' => "Instruments financers i d’assegurances",
            'shortname'=> "Instruments financers i d’assegurances",
            'code' =>  'GAD_MP4_UF2',
            'number' => 2,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 66
        ]);

        Subject::create([
            'name' => "Operacions financeres bàsiques",
            'shortname'=> "Operacions financeres bàsiques",
            'code' =>  'GAD_MP4_UF3',
            'number' => 3,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        $group = SubjectGroup::firstOrCreate([
            'shortname' => 'Tècnica comptable',
            'name' => 'Tècnica comptable',
            'code' =>  'GAD_MP5',
            'number' => 5,
            'study_id' => $study->id,
            'hours' => 165,
            'free_hours' => 0, // Lliure disposició
            'week_hours' => 0,
            'start' => $mp_start_date,
            'end' => $mp_end_date,
            'type' => 'Normal'
        ]);

        Subject::create([
            'name' => "Patrimoni i metodologia comptable",
            'shortname'=> "Patrimoni i metodologia comptable",
            'code' =>  'GAD_MP5_UF1',
            'number' => 1,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        Subject::create([
            'name' => "Cicle comptable bàsic",
            'shortname'=> "Cicle comptable bàsic",
            'code' =>  'GAD_MP5_UF2',
            'number' => 2,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        Subject::create([
            'name' => "Cicle comptable mitjà",
            'shortname'=> "Cicle comptable mitjà",
            'code' =>  'GAD_MP5_UF3',
            'number' => 3,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 99
        ]);

        $group = SubjectGroup::firstOrCreate([
            'shortname' => 'Tractament informàtic',
            'name' => 'Tractament informàtic',
            'code' =>  'GAD_MP7',
            'number' => 7,
            'study_id' => $study->id,
            'hours' => 231,
            'free_hours' => 0, // Lliure disposició
            'week_hours' => 0,
            'start' => $mp_start_date,
            'end' => $mp_end_date,
            'type' => 'Normal'
        ]);

        Subject::create([
            'name' => "Tecnologia i comunicacions digitals",
            'shortname'=> "Tecnologia i comunicacions digitals",
            'code' =>  'GAD_MP7_UF1',
            'number' => 1,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        Subject::create([
            'name' => "Ordinografia i gravació de dades",
            'shortname'=> "Ordinografia i gravació de dades",
            'code' =>  'GAD_MP7_UF2',
            'number' => 2,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        Subject::create([
            'name' => "Tractament de la informació escrita i numèrica",
            'shortname'=> "Tractament de la informació escrita i numèrica",
            'code' =>  'GAD_MP7_UF3',
            'number' => 3,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 66
        ]);

        Subject::create([
            'name' => "Tractament de dades i integració d’aplicacions",
            'shortname'=> "Tractament de dades i integració d’aplicacions",
            'code' =>  'GAD_MP7_UF4',
            'number' => 4,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        Subject::create([
            'name' => "Presentacions multimèdia de continguts",
            'shortname'=> "Presentacions multimèdia de continguts",
            'code' =>  'GAD_MP7_UF5',
            'number' => 5,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        Subject::create([
            'name' => "Eines d’internet per a l’empresa",
            'shortname'=> "Eines d’internet per a l’empresa",
            'code' =>  'GAD_MP7_UF6',
            'number' => 6,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        $group = SubjectGroup::firstOrCreate([
            'shortname' => 'Formació i orientació laboral',
            'name' => 'Formació i orientació laboral',
            'code' =>  'GAD_MP12',
            'number' => 12,
            'study_id' => $study->id,
            'hours' => 99,
            'free_hours' => 0, // Lliure disposició
            'week_hours' => 0,
            'start' => $mp_start_date,
            'end' => $mp_end_date,
            'type' => 'Externes'
        ]);

        Subject::create([
            'name' => "Incorporació al treball",
            'shortname'=> "Incorporació al treball",
            'code' =>  'GAD_MP12_UF1',
            'number' => 1,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 66
        ]);

        Subject::create([
            'name' => "Prevenció dels riscos laborals",
            'shortname'=> "Prevenció dels riscos laborals",
            'code' =>  'GAD_MP12_UF2',
            'number' => 2,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        $group = SubjectGroup::firstOrCreate([
            'shortname' => 'Comunicació empresarial i atenció al client',
            'name' => 'Comunicació empresarial i atenció al client',
            'code' =>  'GAD_MP1',
            'number' => 1,
            'study_id' => $study->id,
            'hours' => 165,
            'free_hours' => 0, // Lliure disposició
            'week_hours' => 0,
            'start' => $mp_start_date,
            'end' => $mp_end_date,
            'type' => 'Normal'
        ]);

        Subject::create([
            'name' => "Comunicació empresarial oral",
            'shortname'=> "Comunicació empresarial oral",
            'code' =>  'GAD_MP1_UF1',
            'number' => 1,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        Subject::create([
            'name' => "Comunicació empresarial escrita",
            'shortname'=> "Comunicació empresarial escrita",
            'code' =>  'GAD_MP1_UF2',
            'number' => 2,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 66
        ]);

        Subject::create([
            'name' => "Sistemes d’arxiu",
            'shortname'=> "Sistemes d’arxiu",
            'code' =>  'GAD_MP1_UF3',
            'number' => 3,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        Subject::create([
            'name' => "Atenció al client/usuari",
            'shortname'=> "Atenció al client/usuari",
            'code' =>  'GAD_MP1_UF4',
            'number' => 4,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        $group = SubjectGroup::firstOrCreate([
            'shortname' => 'Tractament de la documentació comptable',
            'name' => 'Tractament de la documentació comptable',
            'code' =>  'GAD_MP6',
            'number' => 6,
            'study_id' => $study->id,
            'hours' => 132,
            'free_hours' => 0, // Lliure disposició
            'week_hours' => 0,
            'start' => $mp_start_date,
            'end' => $mp_end_date,
            'type' => 'Normal'
        ]);

        Subject::create([
            'name' => "Preparació i codificació comptable",
            'shortname'=> "Preparació i codificació comptable",
            'code' =>  'GAD_MP6_UF1',
            'number' => 1,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course2->id,
            'type_id' => 1,
            'hours' => 40
        ]);

        Subject::create([
            'name' => "Registre comptable",
            'shortname'=> "Registre comptable",
            'code' =>  'GAD_MP6_UF2',
            'number' => 2,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course2->id,
            'type_id' => 1,
            'hours' => 60
        ]);

        Subject::create([
            'name' => "Comptes anuals bàsics",
            'shortname'=> "Comptes anuals bàsics",
            'code' =>  'GAD_MP6_UF3',
            'number' => 3,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course2->id,
            'type_id' => 1,
            'hours' => 16
        ]);

        Subject::create([
            'name' => "Verificació i control intern",
            'shortname'=> "Verificació i control intern",
            'code' =>  'GAD_MP6_UF4',
            'number' => 4,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course2->id,
            'type_id' => 1,
            'hours' => 16
        ]);

        $group = SubjectGroup::firstOrCreate([
            'shortname' => 'Operacions administratives de suport',
            'name' => 'Operacions administratives de suport',
            'code' =>  'GAD_MP8',
            'number' => 8,
            'study_id' => $study->id,
            'hours' => 66,
            'free_hours' => 0, // Lliure disposició
            'week_hours' => 0,
            'start' => $mp_start_date,
            'end' => $mp_end_date,
            'type' => 'Normal'
        ]);

        Subject::create([
            'name' => "Selecció i tractament de la informació",
            'shortname'=> "Selecció i tractament de la informació",
            'code' =>  'GAD_MP8_UF1',
            'number' => 1,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course2->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        Subject::create([
            'name' => "Operacions logístiques de suport administratiu",
            'shortname'=> "Operacions logístiques de suport administratiu",
            'code' =>  'GAD_MP8_UF2',
            'number' => 2,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course2->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        $group = SubjectGroup::firstOrCreate([
            'shortname' => 'Anglès',
            'name' => 'Anglès',
            'code' =>  'GAD_MP9',
            'number' => 9,
            'study_id' => $study->id,
            'hours' => 99,
            'free_hours' => 0, // Lliure disposició
            'week_hours' => 0,
            'start' => $mp_start_date,
            'end' => $mp_end_date,
            'type' => 'Normal'
        ]);

        Subject::create([
            'name' => "Anglès tècnic",
            'shortname'=> "Anglès tècnic",
            'code' =>  'GAD_MP9_UF1',
            'number' => 1,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course2->id,
            'type_id' => 1,
            'hours' => 99
        ]);

        $group = SubjectGroup::firstOrCreate([
            'shortname' => 'Empresa i administració',
            'name' => 'Empresa i administració',
            'code' =>  'GAD_MP10',
            'number' => 10,
            'study_id' => $study->id,
            'hours' => 165,
            'free_hours' => 0, // Lliure disposició
            'week_hours' => 0,
            'start' => $mp_start_date,
            'end' => $mp_end_date,
            'type' => 'Normal'
        ]);

        Subject::create([
            'name' => "Innovació i emprenedoria",
            'shortname'=> "Innovació i emprenedoria",
            'code' =>  'GAD_MP10_UF1',
            'number' => 1,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course2->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        Subject::create([
            'name' => "Empresa i activitat econòmica",
            'shortname'=> "Empresa i activitat econòmica",
            'code' =>  'GAD_MP10_UF2',
            'number' => 2,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course2->id,
            'type_id' => 1,
            'hours' => 24
        ]);

        Subject::create([
            'name' => "Administracions públiques",
            'shortname'=> "Administracions públiques",
            'code' =>  'GAD_MP10_UF3',
            'number' => 3,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course2->id,
            'type_id' => 1,
            'hours' => 60
        ]);

        Subject::create([
            'name' => "Fiscalitat empresarial bàsica",
            'shortname'=> "Fiscalitat empresarial bàsica",
            'code' =>  'GAD_MP10_UF4',
            'number' => 4,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course2->id,
            'type_id' => 1,
            'hours' => 48
        ]);

        $group = SubjectGroup::firstOrCreate([
            'shortname' => "Empresa a l'aula",
            'name' => "Empresa a l'aula",
            'code' =>  'GAD_MP11',
            'number' => 11,
            'study_id' => $study->id,
            'hours' => 132,
            'free_hours' => 0, // Lliure disposició
            'week_hours' => 0,
            'start' => $mp_start_date,
            'end' => $mp_end_date,
            'type' => 'Síntesi'
        ]);

        Subject::create([
            'name' => "Empresa a l'aula",
            'shortname'=> "Empresa a l'aula",
            'code' =>  'GAD_MP11_UF1',
            'number' => 1,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course2->id,
            'type_id' => 1,
            'hours' => 132
        ]);

        $group = SubjectGroup::firstOrCreate([
            'shortname' => "Formació en centres de treball",
            'name' => "Formació en centres de treball",
            'code' =>  'GAD_MP13',
            'number' => 13,
            'study_id' => $study->id,
            'hours' => 350,
            'free_hours' => 0, // Lliure disposició
            'week_hours' => 0,
            'start' => $mp_start_date,
            'end' => $mp_end_date,
            'type' => 'FCT'
        ]);

        Subject::create([
            'name' => "Formació en centres de treball",
            'shortname'=> "Formació en centres de treball",
            'code' =>  'GAD_MP13_UF1',
            'number' => 1,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course2->id,
            'type_id' => 1,
            'hours' => 350
        ]);

        $study = Study::firstOrCreate([
            'name' => 'Administració i finances',
            'code' => 'AIF',
            'law_id' => $loe->id
        ]);

        $course1 = Course::firstOrCreate([
            'code' => '1AIF',
            'name' => 'Administració i finances',
            'order' => 1
        ]);

        $course2 = Course::firstOrCreate([
            'code' => '2AIF',
            'name' => 'Administració i finances',
            'order' => 2
        ]);

        $group = SubjectGroup::firstOrCreate([
            'shortname' => 'Comunicació i atenció al client',
            'name' => 'Comunicació i atenció al client',
            'code' =>  'AIF_MP1',
            'number' => 1,
            'study_id' => $study->id,
            'hours' => 132,
            'free_hours' => 0, // Lliure disposició
            'week_hours' => 0,
            'start' => $mp_start_date,
            'end' => $mp_end_date,
            'type' => 'Normal'
        ]);

        Subject::create([
            'name' => 'Processos de comunicació oral a l’empresa i atenció al client',
            'shortname'=> 'Processos de comunicació oral a l’empresa i atenció al client',
            'code' =>  'AIF_MP1_UF1',
            'number' => 1,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 55
        ]);

        Subject::create([
            'name' => 'Processos de comunicació escrita a l’empresa i atenció al
client',
            'shortname'=> 'Processos de comunicació escrita a l’empresa i atenció al
client',
            'code' =>  'AIF_MP1_UF2',
            'number' => 2,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 44
        ]);

        Subject::create([
            'name' => 'Gestió documental, arxiu i registre',
            'shortname'=> 'Gestió documental, arxiu i registre',
            'code' =>  'AIF_MP1_UF3',
            'number' => 3,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        $group = SubjectGroup::firstOrCreate([
            'shortname' => 'Gestió de la documentació jurídica i empresarial',
            'name' => 'Gestió de la documentació jurídica i empresarial',
            'code' =>  'AIF_MP2',
            'number' => 2,
            'study_id' => $study->id,
            'hours' => 99,
            'free_hours' => 0, // Lliure disposició
            'week_hours' => 0,
            'start' => $mp_start_date,
            'end' => $mp_end_date,
            'type' => 'Normal'
        ]);

        Subject::create([
            'name' => 'Organització de la documentació jurídica empresarial',
            'shortname'=> 'Organització de la documentació jurídica empresarial',
            'code' =>  'AIF_MP2_UF1',
            'number' => 1,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        Subject::create([
            'name' => 'Contractació empresarial',
            'shortname'=> 'Contractació empresarial',
            'code' =>  'AIF_MP2_UF2',
            'number' => 2,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        Subject::create([
            'name' => 'Tramitació davant les administracions públiques',
            'shortname'=> 'Tramitació davant les administracions públiques',
            'code' =>  'AIF_MP2_UF3',
            'number' => 3,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        $group = SubjectGroup::firstOrCreate([
            'shortname' => "Procés integral de l'activitat comercial",
            'name' => "Procés integral de l'activitat comercial",
            'code' =>  'AIF_MP3',
            'number' => 3,
            'study_id' => $study->id,
            'hours' => 264,
            'free_hours' => 0, // Lliure disposició
            'week_hours' => 0,
            'start' => $mp_start_date,
            'end' => $mp_end_date,
            'type' => 'Normal'
        ]);

        Subject::create([
            'name' => 'Patrimoni i metodologia comptable',
            'shortname'=> 'Patrimoni i metodologia comptable',
            'code' =>  'AIF_MP3_UF1',
            'number' => 1,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        Subject::create([
            'name' => 'Fiscalitat empresarial',
            'shortname'=> 'Fiscalitat empresarial',
            'code' =>  'AIF_MP3_UF2',
            'number' => 2,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        Subject::create([
            'name' => 'Gestió administrativa de les operacions de compravenda i
tresoreria',
            'shortname'=> 'Gestió administrativa de les operacions de compravenda i
tresoreria',
            'code' =>  'AIF_MP3_UF3',
            'number' => 3,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 66
        ]);

        Subject::create([
            'name' => 'Registre comptable i comptes anuals',
            'shortname'=> 'Registre comptable i comptes anuals',
            'code' =>  'AIF_MP3_UF4',
            'number' => 4,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 132
        ]);

        $group = SubjectGroup::firstOrCreate([
            'shortname' => "Recursos humans i responsabilitat social",
            'name' => "Recursos humans i responsabilitat social",
            'code' =>  'AIF_MP4',
            'number' => 4,
            'study_id' => $study->id,
            'hours' => 264,
            'free_hours' => 0, // Lliure disposició
            'week_hours' => 0,
            'start' => $mp_start_date,
            'end' => $mp_end_date,
            'type' => 'Normal'
        ]);

        Subject::create([
            'name' => 'Processos administratius de recursos humans',
            'shortname'=> 'Processos administratius de recursos humans',
            'code' =>  'AIF_MP4_UF1',
            'number' => 1,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        Subject::create([
            'name' => 'Reclutament i desenvolupament professional',
            'shortname'=> 'Reclutament i desenvolupament professional',
            'code' =>  'AIF_MP4_UF2',
            'number' => 2,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        $group = SubjectGroup::firstOrCreate([
            'shortname' => 'Ofimàtica i procés de la informació',
            'name' => 'Ofimàtica i procés de la informació',
            'code' =>  'AIF_MP5',
            'number' => 5,
            'study_id' => $study->id,
            'hours' => 165,
            'free_hours' => 0, // Lliure disposició
            'week_hours' => 0,
            'start' => $mp_start_date,
            'end' => $mp_end_date,
            'type' => 'Normal'
        ]);

        Subject::create([
            'name' => 'Tecnologia i comunicacions digitals i processament de dades',
            'shortname'=> 'Tecnologia i comunicacions digitals i processament de dades',
            'code' =>  'AIF_MP5_UF1',
            'number' => 1,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        Subject::create([
            'name' => 'Tractament avançat, arxiu i presentació de la informació
escrita d’aplicacions',
            'shortname'=> 'Tractament avançat, arxiu i presentació de la informació
escrita d’aplicacions',
            'code' =>  'AIF_MP5_UF2',
            'number' => 2,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 66
        ]);

        Subject::create([
            'name' => 'Gestió de bases de dades, disseny de fulls de càlcul i
integració d’aplicacions',
            'shortname'=> 'Gestió de bases de dades, disseny de fulls de càlcul i
integració d’aplicacions',
            'code' =>  'AIF_MP5_UF3',
            'number' => 3,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 66
        ]);

        $group = SubjectGroup::firstOrCreate([
            'shortname' => 'Anglès tècnic',
            'name' => 'Anglès tècnic',
            'code' =>  'AIF_MP6',
            'number' => 6,
            'study_id' => $study->id,
            'hours' => 132,
            'free_hours' => 0, // Lliure disposició
            'week_hours' => 0,
            'start' => $mp_start_date,
            'end' => $mp_end_date,
            'type' => 'Externes'
        ]);

        Subject::create([
            'name' => 'Anglès tècnic',
            'shortname'=> 'Anglès tècnic',
            'code' =>  'AIF_MP6_UF1',
            'number' => 1,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 132
        ]);

        $group = SubjectGroup::firstOrCreate([
            'shortname' => 'Gestió de recursos humans',
            'name' => 'Gestió de recursos humans',
            'code' =>  'AIF_MP7',
            'number' => 6,
            'study_id' => $study->id,
            'hours' => 99,
            'free_hours' => 0, // Lliure disposició
            'week_hours' => 0,
            'start' => $mp_start_date,
            'end' => $mp_end_date,
            'type' => 'Normal'
        ]);

        Subject::create([
            'name' => 'Procés de contractació',
            'shortname'=> 'Procés de contractació',
            'code' =>  'AIF_MP7_UF1',
            'number' => 1,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        Subject::create([
            'name' => 'Retribucions, nòmines i obligacions oficials',
            'shortname'=> 'Retribucions, nòmines i obligacions oficials',
            'code' =>  'AIF_MP7_UF2',
            'number' => 2,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course2->id,
            'type_id' => 1,
            'hours' => 66
        ]);

        $group = SubjectGroup::firstOrCreate([
            'shortname' => 'Gestió financera',
            'name' => 'Gestió financera',
            'code' =>  'AIF_MP8',
            'number' => 8,
            'study_id' => $study->id,
            'hours' => 165,
            'free_hours' => 0, // Lliure disposició
            'week_hours' => 0,
            'start' => $mp_start_date,
            'end' => $mp_end_date,
            'type' => 'Normal'
        ]);

        Subject::create([
            'name' => 'Anàlisi i previsió financeres',
            'shortname'=> 'Anàlisi i previsió financeres',
            'code' =>  'AIF_MP8_UF1',
            'number' => 1,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        Subject::create([
            'name' => "Productes del mercat financer i d'assegurances",
            'shortname'=> "Productes del mercat financer i d'assegurances",
            'code' =>  'AIF_MP8_UF2',
            'number' => 2,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course2->id,
            'type_id' => 1,
            'hours' => 66
        ]);

        Subject::create([
            'name' => "Fonts de finançament i selecció d'inversions",
            'shortname'=> "Fonts de finançament i selecció d'inversions",
            'code' =>  'AIF_MP8_UF3',
            'number' => 3,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course2->id,
            'type_id' => 1,
            'hours' => 66
        ]);

        $group = SubjectGroup::firstOrCreate([
            'shortname' => 'Compabilitat i fiscalitat',
            'name' => 'Compabilitat i fiscalitat',
            'code' =>  'AIF_MP9',
            'number' => 9,
            'study_id' => $study->id,
            'hours' => 198,
            'free_hours' => 0, // Lliure disposició
            'week_hours' => 0,
            'start' => $mp_start_date,
            'end' => $mp_end_date,
            'type' => 'Normal'
        ]);

        Subject::create([
            'name' => 'Comptabilitat financera, fiscalitat i auditoria',
            'shortname'=> 'Comptabilitat financera, fiscalitat i auditoria',
            'code' =>  'AIF_MP9_UF1',
            'number' => 1,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 132
        ]);

        Subject::create([
            'name' => "Comptabilitat de costos",
            'shortname'=> "Comptabilitat de costos",
            'code' =>  'AIF_MP9_UF2',
            'number' => 2,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course2->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        Subject::create([
            'name' => "Anàlisi econòmic, patrimonial i financer",
            'shortname'=> "Anàlisi econòmic, patrimonial i financer",
            'code' =>  'AIF_MP9_UF3',
            'number' => 3,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course2->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        $group = SubjectGroup::firstOrCreate([
            'shortname' => 'Gestió logística i comercial',
            'name' => 'Gestió logística i comercial',
            'code' =>  'AIF_MP10',
            'number' => 10,
            'study_id' => $study->id,
            'hours' => 99,
            'free_hours' => 0, // Lliure disposició
            'week_hours' => 0,
            'start' => $mp_start_date,
            'end' => $mp_end_date,
            'type' => 'Normal'
        ]);

        Subject::create([
            'name' => 'Planificació de l’aprovisionament',
            'shortname'=> 'Planificació de l’aprovisionament',
            'code' =>  'AIF_MP10_UF1',
            'number' => 1,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        Subject::create([
            'name' => "Selecció i control de proveïdors",
            'shortname'=> "Selecció i control de proveïdors",
            'code' =>  'AIF_MP10_UF2',
            'number' => 2,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course2->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        Subject::create([
            'name' => "Operativa i control de la cadena logística",
            'shortname'=> "Operativa i control de la cadena logística",
            'code' =>  'AIF_MP10_UF3',
            'number' => 3,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course2->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        $group = SubjectGroup::firstOrCreate([
            'shortname' => 'Simulació empresarial',
            'name' => 'Simulació empresarial',
            'code' =>  'AIF_MP11',
            'number' => 11,
            'study_id' => $study->id,
            'hours' => 99,
            'free_hours' => 0, // Lliure disposició
            'week_hours' => 0,
            'start' => $mp_start_date,
            'end' => $mp_end_date,
            'type' => 'Normal'
        ]);

        Subject::create([
            'name' => 'Simulació empresarial',
            'shortname'=> 'Simulació empresarial',
            'code' =>  'AIF_MP11_UF1',
            'number' => 1,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 99
        ]);

        $group = SubjectGroup::firstOrCreate([
            'shortname' => "Projecte d'administració i finances",
            'name' => "Projecte d'administració i finances",
            'code' =>  'AIF_MP13',
            'number' => 13,
            'study_id' => $study->id,
            'hours' => 33,
            'free_hours' => 0, // Lliure disposició
            'week_hours' => 0,
            'start' => $mp_start_date,
            'end' => $mp_end_date,
            'type' => 'Síntesi'
        ]);

        Subject::create([
            'name' => "Projecte d'administració i finances",
            'shortname'=> "Projecte d'administració i finances",
            'code' =>  'AIF_MP13_UF1',
            'number' => 1,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 33
        ]);

        $group = SubjectGroup::firstOrCreate([
            'shortname' => "Formació en centres de treball",
            'name' => "Formació en centres de treball",
            'code' =>  'AIF_MP14',
            'number' => 14,
            'study_id' => $study->id,
            'hours' => 350,
            'free_hours' => 0, // Lliure disposició
            'week_hours' => 0,
            'start' => $mp_start_date,
            'end' => $mp_end_date,
            'type' => 'FCT'
        ]);

        Subject::create([
            'name' => "Formació en centres de treball",
            'shortname'=> "Formació en centres de treball",
            'code' =>  'AIF_MP14_UF1',
            'number' => 1,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 350
        ]);

        $group = SubjectGroup::firstOrCreate([
            'shortname' => 'Formació i orientació laboral',
            'name' => 'Formació i orientació laboral',
            'code' =>  'AIF_MP12',
            'number' => 6,
            'study_id' => $study->id,
            'hours' => 132,
            'free_hours' => 0, // Lliure disposició
            'week_hours' => 0,
            'start' => $mp_start_date,
            'end' => $mp_end_date,
            'type' => 'Externes'
        ]);

        Subject::create([
            'name' => 'Incorporació al treball',
            'shortname'=> 'Incorporació al treball',
            'code' =>  'AIF_MP12_UF1',
            'number' => 1,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course1->id,
            'type_id' => 1,
            'hours' => 66
        ]);

        Subject::create([
            'name' => 'Prevenció de riscos laborals',
            'shortname'=> 'Prevenció de riscos laborals',
            'code' =>  'AIF_MP12_UF2',
            'number' => 2,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course2->id,
            'type_id' => 1,
            'hours' => 33
        ]);

    }
}

if (!function_exists('initialize_fake_subjects')) {
    function initialize_fake_subjects()
    {
        $mp_start_date = '2017-09-15';
        $mp_end_date = '2018-06-01';

        // Per estudis i mòduls:

        // Informàtica
//        Mòdul professional 1: sistemes informàtics

        // http://portaldogc.gencat.cat/utilsEADOP/PDF/6958/1444503.pdf
        // O matrícula TIC

        $loe = Law::firstOrCreate([
            'code' => 'LOE',
            'name' => 'Ley Orgánica de Educación'
        ]);

        $study = Study::firstOrCreate([
            'name' => 'Desenvolupament Aplicacions Multiplataforma',
            'code' => 'DAM',
            'law_id' => $loe->id
        ]);

        // No existeix 1DAM -> és comú amb 1rASIX
//        $course1 = Course::create([
//            'name' => 'Desenvolupament Aplicacions Multiplataforma',
//            'code' => '1DAM'
//        ]);
        $course2 = Course::firstOrCreate([
            'code' => '2DAM',
            'name' => 'Desenvolupament Aplicacions Multiplataforma',
            'order' => 2
        ]);

//        Mòdul professional (Subject Group) 7: desenvolupament d’interfícies
        $group = SubjectGroup::firstOrCreate([
            'shortname' => 'Desenvolupament d’interfícies',
            'name' => 'Desenvolupament d’interfícies',
            'code' =>  'DAM_MP7',
            'number' => 7,
            'study_id' => $study->id,
            'hours' => 99,
            'free_hours' => 0, // Lliure disposició
            'week_hours' => 3,
            'start' => $mp_start_date,
            'end' => $mp_end_date,
            'type' => 'Normal'
        ]);
//        $table->enum('type', ['Normal', 'Externes', 'Síntesi', 'FCT' ])->default('Normal');
//        $table->unsignedTinyInteger('order')->nullable();

        Subject::create([
            'name' => 'Disseny i implementació d’interfícies',
            'shortname'=> 'Disseny i implementació d’interfícies',
            'code' =>  'DAM_MP7_UF1',
            'number' => 1,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course2->id,
            'type_id' => 1,
            'hours' => 79,
            'start' => $mp_start_date,
            'end' => $mp_end_date
        ]);

        Subject::create([
            'name' => 'Preparació i distribució d’aplicacions',
            'shortname'=> 'Preparació i distribució d’aplicacions',
            'code' =>  'DAM_MP7_UF2',
            'number' => 2,
            'subject_group_id' => $group->id,
            'study_id' => $study->id,
            'course_id' => $course2->id,
            'type_id' => 1,
            'hours' => 20
        ]);
    }
}

if (!function_exists('initialize_fake_week_lessons')) {
    /**
     * Week lesson sempre s'omple amb la info de GPuntis però per fer proves va bé aquest helper
     */
    function initialize_fake_week_lessons()
    {
        WeekLesson::create([
            'code' => 'DAM_MP7_3_2100_2200',
            'day' => 3, // Dimecres
            'start' => '21:00:00',
            'end' => '22:00:00',
//            'job_id' => 1 és potencial no assignem encara a cap plaça/profe
            'subject_group_id' =>  SubjectGroup::findByCode('DAM_MP7')->id,
//            'classroom_id' => TODO
//            'location_id' => // No necessari, encara no tinc locations
        ]);

        WeekLesson::create([
            'code' => 'DAM_MP7_5_1530_1730',
            'day' => 5, // divendres
            'start' => '15:30:00',
            'end' => '17:30:00',
            'subject_group_id' =>  SubjectGroup::findByCode('DAM_MP7')->id,
        ]);
    }
}

if (!function_exists('dayOfWeek')) {
    function dayOfWeek($isoNumber) {
        switch ($isoNumber) {
            case 1:
                return 'monday';
            case 2:
                return 'tuesday';
            case 3:
                return 'wednesday';
            case 4:
                return 'thursday';
            case 5:
                return 'friday';
            case 6:
                return 'saturday';
            case 7:
                return 'sunday';
        }
    }
}

if (!function_exists('check_lesson')) {
    function check_lesson($lesson)
    {
//        dd(json_encode($lesson));
        return array_key_exists('id', $lesson) &&
            array_key_exists('subject_id', $lesson) &&
            array_key_exists('job_id', $lesson) &&
            array_key_exists('classroom_id', $lesson) &&
            array_key_exists('start', $lesson) &&
            array_key_exists('end', $lesson) &&
            array_key_exists('created_at', $lesson) &&
            array_key_exists('updated_at', $lesson);
    }
}

if (!function_exists('initialize_fake_lessons')) {
    function initialize_fake_lessons()
    {
        Lesson::firstOrCreate([
            'subject_id' => 1,
            'job_id' => 1,
            'classroom_id' => 1,
            'start' => new Carbon('2018-09-20 19:00:00'),
            'end' => new Carbon('2018-09-20 20:00:00')
        ]);

        Lesson::firstOrCreate([
            'subject_id' => 1,
            'job_id' => 1,
            'classroom_id' => 1,
            'start' => new Carbon('2018-09-21 17:00:00'),
            'end' => new Carbon('2018-09-21 18:00:00')
        ]);

        Lesson::firstOrCreate([
            'subject_id' => 1,
            'job_id' => 1,
            'classroom_id' => 1,
            'start' => new Carbon('2018-09-19 17:00:00'),
            'end' => new Carbon('2018-09-19 18:00:00')
        ]);
    }
}
