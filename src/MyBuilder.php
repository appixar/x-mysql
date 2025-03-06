<?php

class MyBuilder extends Xplend
{
    // queries
    public $queries = array();
    public $queries_mini = array();
    public $queries_color = array();
    // sub arguments
    public $mute = false;
    public $create_database = false; // create db if not exists
    public $create_database_count = 0; // created database
    public $select_database = '';
    public $select_tenant = '';
    // util
    private $actions = 0;
    public $schema_default = array(
        'id' => array(
            'Type' => 'int(11)',
            'Null' => 'NO',
            'Default' => '',
            'Key' => 'PRI',
            'Extra' => 'auto_increment'
        ),
        'str' => array(
            'Type' => 'varchar(64)',
            'Null' => 'YES',
            'Default' => '',
            'Key' => '',
            'Extra' => ''
        ),
        'date' => array(
            'Type' => 'datetime',
            'Null' => 'YES',
            'Default' => '',
            'Key' => '',
            'Extra' => ''
        ),
        'int' => array(
            'Type' => 'int(11)',
            'Null' => 'YES',
            'Default' => '',
            'Key' => '',
            'Extra' => ''
        ),
        'float' => array(
            'Type' => 'float',
            'Null' => 'YES',
            'Default' => '',
            'Key' => '',
            'Extra' => ''
        ),
        'text' => array(
            'Type' => 'longtext',
            'Null' => 'YES',
            'Default' => '',
            'Key' => '',
            'Extra' => ''
        ),
        // SPECIAL FIELDS
        'email' => array(
            'Type' => 'varchar(128)',
            'Null' => 'YES',
            'Default' => '',
            'Key' => '',
            'Extra' => ''
        ),
        'ucwords' => array(
            'Type' => 'varchar(64)',
            'Null' => 'YES',
            'Default' => '',
            'Key' => '',
            'Extra' => ''
        ),
        'phone' => array(
            'Type' => 'varchar(11)',
            'Null' => 'YES',
            'Default' => '',
            'Key' => '',
            'Extra' => ''
        ),
        'cpf' => array(
            'Type' => 'varchar(11)',
            'Null' => 'YES',
            'Default' => '',
            'Key' => '',
            'Extra' => ''
        ),
        'cnpj' => array(
            'Type' => 'varchar(14)',
            'Null' => 'YES',
            'Default' => '',
            'Key' => '',
            'Extra' => ''
        ),
        'alphanumeric' => array(
            'Type' => 'varchar(64)',
            'Null' => 'YES',
            'Default' => '',
            'Key' => '',
            'Extra' => ''
        ),
        'url' => array(
            'Type' => 'text',
            'Null' => 'YES',
            'Default' => '',
            'Key' => '',
            'Extra' => ''
        ),
    );
    // Armazena a única instância (para obter variavel atraves de metodo estatico)
    private static $instance = null;
    public function __construct()
    {
        if (!is_writable(self::DIR_SCHEMA)) {
            //die('ERROR:' . realpath(self::DIR_SCHEMA) . ' is not writeable.' . PHP_EOL);
        }
    }
    // Retorna a única instância da classe (para obter variavel atraves de metodo estatico)
    public static function getInstance()
    {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }
    public static function getParams()
    {
        return self::getInstance()->schema_default;
    }
    public function buildReverse()
    {
        $table = array();
        $r = jwquery("SHOW TABLES");
        for ($i = 0; $i < count($r); $i++) {
            foreach ($r[$i] as $k => $v) $table[] = $v;
        }
        for ($i = 0; $i < count($table); $i++) {
            $field = array();
            $r = jwquery("SHOW COLUMNS FROM {$table[$i]}");
            for ($x = 0; $x < count($r); $x++) {
                $f_name = $r[$x]['Field'];
                $f_type = $r[$x]['Field'];
                $f_null = $r[$x]['Field'];
                $f_key = $r[$x]['Field'];
                $f_extra = $r[$x]['Field'];
            }
        }
    }
    public function up($argx)
    {
        global $_APP;

        // sub --arguments
        if (@$argx['--mute']) $this->mute = true;
        if (@$argx['--create']) $this->create_database = true;
        if (@$argx['--name']) $this->select_database = $argx['--name'];
        if (@$argx['--tenant']) $this->select_tenant = $argx['--tenant'];
        //prex($argx);

        if (!@is_array($_APP['MYSQL'])) {
            Mason::say("Ops! config is missing.", "red");
            Mason::say("Please, verify: modules/mysql/config/mysql.yml", "red");
            exit;
        }

        foreach ($_APP['MYSQL'] as $db_id => $db_conf) {

            // FILTER: TENANT
            if ($this->select_tenant) {
                if (!@$db_conf['TENANT_KEYS']) continue;
            }
            // FILTER: DATABASE NAME
            if ($this->select_database) {
                if ($this->select_database !== $db_conf['NAME'] and !@$db_conf['TENANT_KEYS']) {
                    continue;
                }
            }
            Mason::header("► MySQL '$db_id' ...", 'cyan');

            // GET SPECIFIC PATH
            if (@$db_conf['PATH']) {
                if (!is_array($db_conf['PATH'])) $db_conf['PATH'] = [$db_conf['PATH']];
                for ($i = 0; $i < count($db_conf['PATH']); $i++) {
                    $db_conf['PATH'][$i] = realpath(__DIR__ . '/../../../' . $db_conf['PATH'][$i] . '/');
                }
                $databasePaths = $db_conf['PATH'];
            }
            // GET ALL DB PATHS
            else $databasePaths = Xplend::findPathsByType("database");

            // MYSQL KEY HAVE AN tenant KEY?
            $multi_tenant = false;
            if (strpos($db_conf['NAME'], '<TENANT_KEY>') or strpos($db_conf['HOST'], '<TENANT_KEY>') or strpos($db_conf['USER'], '<TENANT_KEY>')) {
                $multi_tenant = true;
            }
            // MULTI TENANT
            if ($multi_tenant) {
                //-------------------------------------------
                // GET TENANT KEYS FROM CONTROLLER
                //-------------------------------------------
                if (isset($db_conf['TENANT_KEYS']['CONTROLLER'])) {
                    $controller = $db_conf['TENANT_KEYS']['CONTROLLER'];
                    $tenant_keys = new $controller();
                }
                //-------------------------------------------
                // GET TENANT KEYS FROM JSON URL
                //-------------------------------------------
                elseif (isset($db_conf['TENANT_KEYS']['JSON_URL'])) {
                    $json_url = $db_conf['TENANT_KEYS']['JSON_URL'];
                    if (@!explode('http://', $json_url)[1] and @!explode('https://', $json_url)[1]) {
                        $json_url = $_APP['URL'] . $json_url;
                    }
                    $data = file_get_contents($json_url);
                    $tenant_keys = json_decode($data);
                }
                //-------------------------------------------
                // GET TENANT KEYS FROM DB
                //-------------------------------------------
                else {
                    if (!@isset($db_conf['TENANT_KEYS']['DBKEY']) or !@$db_conf['TENANT_KEYS']['TABLE'] or !@$db_conf['TENANT_KEYS']['FIELD'] or !@$db_conf['TENANT_KEYS']['WHERE']) {
                        Mason::say("✗ Missing wildcard parameters", 'red');
                        goto next_db;
                    }
                    if (!@$_APP['MYSQL'][$db_conf['TENANT_KEYS']['DBKEY']]) {
                        Mason::say("✗ MySQL '{$db_conf['TENANT_KEYS']['DBKEY']}' not found. Can't build wildcard loop.", 'red');
                        goto next_db;
                    }
                    // CREATE WILDCARD LOOP
                    $my_temp = new my(['db_key' => $db_conf['TENANT_KEYS']['DBKEY']]);
                    $tenant_res = $my_temp->query("SELECT {$db_conf['TENANT_KEYS']['FIELD']} FROM {$db_conf['TENANT_KEYS']['TABLE']} WHERE {$db_conf['TENANT_KEYS']['WHERE']}");
                    $tenant_loop = array();
                    foreach ($tenant_res as $res) {
                        if (@$res[$db_conf['TENANT_KEYS']['FIELD']]) {
                            $tenant_keys[] = $res[$db_conf['TENANT_KEYS']['FIELD']];
                        }
                    }
                }
            }
            // MOUNT ARRAY TENANT KEYS
            if (@$tenant_keys[0]) {
                $tenant_loop = array();
                $x = 0;
                foreach ($tenant_keys as $key) {
                    $tenant_loop[$x] = $db_conf;
                    $tenant_loop[$x]['TENANT_KEY'] = $key;
                    $tenant_loop[$x]['NAME'] = str_replace('<TENANT_KEY>', $key, $db_conf['NAME']);
                    $tenant_loop[$x]['HOST'] = str_replace('<TENANT_KEY>', $key, $db_conf['HOST']);
                    $tenant_loop[$x]['USER'] = str_replace('<TENANT_KEY>', $key, $db_conf['USER']);
                    $x++;
                }
                #prex($tenant_loop);
            }
            // DONT EXISTS TENANT KEYS
            else {
                $tenant_loop[0] = $db_conf;
            }
            //--------------------------------------------
            // FOUND TENANT FOCUS
            //--------------------------------------------
            // FILTER: TENANT
            if ($this->select_tenant) {
                $tenant_found = 0;
                foreach ($tenant_loop as $db) {
                    if ($db['TENANT_KEY'] === $this->select_tenant) {
                        unset($tenant_loop);
                        $tenant_loop[0] = $db;
                        $tenant_found++;
                        break;
                    }
                }
                if (!$tenant_found) {
                    Mason::say("- Searching '{$this->select_tenant}' in " . count($tenant_loop) . " tenants...");
                    Mason::say("- Not Found!");
                    Mason::say("");
                    goto next_db;
                }
            }
            // FILTER: DATABASE NAME
            if ($this->select_database) {
                $db_found = 0;
                foreach ($tenant_loop as $db) {
                    if ($db['NAME'] === $this->select_database) {
                        unset($tenant_loop);
                        $tenant_loop[0] = $db;
                        $db_found++;
                        break;
                    }
                }
                if (!$db_found) {
                    Mason::say("- Searching database '{$this->select_database}' in " . count($tenant_loop) . " tenants...");
                    Mason::say("- Not Found!");
                    Mason::say("");
                    goto next_db;
                }
            }
            //--------------------------------------------
            //
            // DATABASE QUERY LOOP
            //
            //--------------------------------------------
            foreach ($tenant_loop as $db) {

                // RESET DEBUG DATA
                $this->queries = array();
                $this->queries_mini = array();
                $this->queries_color = array();
                $this->actions = 0;

                // CONNECT CONF: DB KEY
                $my_conf = ['db_key' => $db_id];
                // CONNECT CONF: CREATE DB IF NOT EXISTS
                if ($this->create_database) $my_conf['ignore-database'] = 1;
                // CONNECT CONF: TENANT DATA
                $tenant_key = '';
                if ($multi_tenant) {
                    $tenant_key = $db['TENANT_KEY'];
                    $my_conf['tenant_key'] = $tenant_key;
                    Mason::header("→ Start $db_id/$tenant_key", 'blue');
                }
                $my = new my($my_conf);

                // CREATE DB IF NOT EXISTS
                if ($this->create_database) {
                    //Mason::say($db['NAME']);
                    $find_db = $my->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = :name", ['name' => $db['NAME']]);
                    // NOT FOUND: CREATE
                    if (@!$find_db[0]) {
                        //prex($db);
                        $this->createDatabase($db['NAME'], $my);
                        goto execute;
                    }
                    // FOUND: USE
                    else {
                        $my->query("USE {$db['NAME']}");
                    }
                }

                // SHOW CURRENT TABLES
                $tables_real = array();
                $t = $my->query("SHOW TABLES");
                for ($i = 0; $i < count($t); $i++) foreach ($t[$i] as $k) $tables_real[] = $k;

                // SCANDIR /SCHEMA
                $tables_new = array();
                foreach ($databasePaths as $path) {

                    if (file_exists($path) and is_dir($path)) {
                        $table_files = scandir($path);
                        foreach ($table_files as $fn) {
                            $fp = "$path/$fn";
                            if (is_file($fp)) {

                                if (!$this->mute) Mason::say("");
                                if (!$this->mute) Mason::say("⍐ Processing: " . realpath($fp), 'magenta');

                                // CHECK YML FILE INTEGRITY
                                //if (!$this->checkFileIntegrity($fp)) goto nextFile;

                                // new fields
                                $data = @yaml_parse(file_get_contents($fp));

                                // MULTIPLE TABLES ON SINGLE FILE?
                                if (!is_array($data)) {
                                    if (!$this->mute) Mason::say("* Invalid file format. Ignored.", 'yellow');
                                    goto nextFile;
                                }

                                foreach ($data as $table_name => $table_cols) {

                                    // add prefix to ~tableName
                                    if (substr($table_name, 0, 1) === '~') {
                                        $table_name = $db['PREF'] . substr($table_name, 1);
                                    }

                                    // increment tables_new to delete old tables
                                    $tables_new[] = $table_name;

                                    // reforce bugfix (old yml format)
                                    if (@$table_cols[0]) {
                                        if (!$this->mute) Mason::say("* Invalid file format. Ignored.", 'yellow');
                                        goto nextFile;
                                    }

                                    // convert fields
                                    $field = $this->convertField($table_cols);
                                    if (!$field) goto nextTable;

                                    // ignore table changes?
                                    $ignore = @$table_cols['~ignore'];
                                    if ($ignore) goto nextTable;

                                    // current fields
                                    $field_curr = array();
                                    // table exists?
                                    if (in_array($table_name, $tables_real)) {
                                        $r = $my->query("SHOW COLUMNS FROM $table_name");
                                        if ($r[0]) {
                                            for ($x = 0; $x < count($r); $x++) $field_curr[$r[$x]['Field']] = $r[$x];
                                            //pre($field_curr);
                                            $this->updateTable($table_name, $field, $field_curr, $my);
                                        }
                                    }
                                    // table dont exists
                                    else $this->createTable($table_name, $field, $my);
                                    nextTable:
                                }
                                nextFile:
                            }
                        }
                    } // dir /database exists

                    #print_r($tables_real);
                    #print_r($tables_new);
                    #exit;
                }

                // DELETE TABLES THAT ARE NOT IN /SCHEMA
                foreach ($tables_real as $k) {
                    if (!in_array($k, $tables_new)) $this->deleteTable($k, $my);
                }

                // CONFIRM CHANGES
                execute:
                if (!empty($this->queries)) {
                    Mason::say("");
                    Mason::say("→ {$this->actions} requested actions for: $db_id/$tenant_key");
                    Mason::say("→ Please, verify:");
                    Mason::say("");
                    for ($z = 0; $z < count($this->queries); $z++) {
                        if ($this->queries_mini[$z]) $qr = $this->queries_mini[$z];
                        else $qr = $this->queries[$z];
                        Mason::say("→ $qr", $this->queries_color[$z]);
                    }
                    echo PHP_EOL;
                    echo "Are you sure you want to do this? ☝" . PHP_EOL;
                    echo "0: No" . PHP_EOL;
                    echo "1: Yes" . PHP_EOL;
                    //echo "2: Yes to all" . PHP_EOL;
                    echo "Choose an option: ";
                    $handle = fopen("php://stdin", "r");
                    $line = fgets($handle);
                    fclose($handle);
                    if (trim($line) == 0) {
                        echo "Aborting!" . PHP_EOL;
                        goto next_tenant;
                    }
                    //----------------------------------------------
                    // RUN QUERIES!
                    //----------------------------------------------
                    for ($z = 0; $z < count($this->queries); $z++) {
                        $my->query($this->queries[$z]);
                    }
                } // CONFIRM 
                Mason::header("❤ Finished $db_id/$tenant_key. Changes: {$this->actions}", 'blue');
                next_tenant:
            }
            next_db:
        }
        if ($this->create_database_count > 0) {
            Mason::header("Possible new databases: {$this->create_database_count}. Reloading...", 'cyan');
            $this->create_database_count = 0;
            $this->up(['--mute' => true]);
        }
    }
    /*public function populate()
    {
        // SCANDIR /SCHEMA
        $tables = scandir(self::DIR_SCHEMA);
        for ($i = 0; $i < count($tables); $i++) {
            $fn = $tables[$i];
            $fp = self::DIR_SCHEMA . $fn;
            if (is_file($fp)) {
                // new fields
                $data = yaml_parse(file_get_contents($fp));
                pre($data['data']);
            }
        }
    }*/
    //-------------------------------------------------------
    // CONVERT FIELD YML TO PHP MYSQL DEFAULT ARRAY
    //-------------------------------------------------------
    private function convertField($field)
    {
        // create new fields
        $new_field = array();
        if (!is_array($field)) goto convertFieldEnd;
        foreach ($field as $k => $v) {

            // field type
            $type = explode(" ", $v)[0];
            $type = explode("/", $type)[0];
            $type_real = @explode("(", @$this->schema_default[$type]['Type'])[0];
            if (!$type_real) {
                $type_real = $type;
                $this->schema_default[$type_real] = [
                    'Type' => '',
                    'Null' => '',
                    'Default' => '',
                    'Extra' => ''
                ];
            }

            // type is null
            if (!$type) {
                Mason::say("* Ignoring field $k: type is null.", "yellow");
                continue;
            }
            // field length
            $len = @explode(" ", $v)[0];
            $len = @explode("/", $len)[1];
            if ($len) $type_real = "$type_real($len)";
            else $type_real = @$this->schema_default[$type]['Type'];

            // field required (not null?)
            $req = array_search('required', explode(" ", $v));
            if ($req !== false) {
                $null = "NO";
                $default = "";
            } else {
                $null = $this->schema_default[$type]['Null'];
                $default = $this->schema_default[$type]['Default'];
            }
            // indexes(multi) & uniques
            $index = array_search('index', explode(" ", $v));
            $uni = array_search('unique', explode(" ", $v));
            if ($uni !== false) $key = "UNI";
            elseif ($index !== false) $key = "MUL";
            else $key = @$this->schema_default[$type]['Key'];
            $new_field[$k] = array(
                'Field' => $k,
                'Type' => $type_real,
                'Null' => $null,
                'Key' => $key,
                'Default' => $default,
                'Extra' => @$this->schema_default[$type]['Extra'],
            );
            //print_r($new_field);
            //}
        }
        convertFieldEnd:
        return $new_field;
    }
    //-------------------------------------------------------
    // UPDATE TABLE : RUN QUERY
    //-------------------------------------------------------
    private function updateTable($table, $field, $field_curr, $my)
    {
        if (!$this->mute) Mason::header("∴ $table", 'blue');
        $query = '';

        // REMOVE FIELDS
        foreach ($field_curr as $k => $v) {
            if (!@$field[$k]) {
                $query = "ALTER TABLE `$table` DROP `$k`;";
                $this->queries[] = $query;
                $this->queries_mini[] = false;
                $this->queries_color[] = 'yellow';
                if (!$this->mute) Mason::say("→ $query", 'yellow');
                //$my->query($query);
                $this->actions++;
            }
        }
        // CREATE + UPDATE FIELDS
        $after = "";
        foreach ($field as $k => $v) {

            // CHECK IF EXISTS DIFFERENCES
            if (@$field_curr[$k]) {
                $diff = array_diff($v, $field_curr[$k]);
                // IGNORE INT LENGTH (CAN´T FIND A SOLUTION FOR THIS)
                // BUGFIX...
                if (@explode("(", $diff['Type'])[0] === "int" and @explode("(", $field_curr[$k]['Type'])[0] === "int") {
                    if (!$this->mute) Mason::say("<green>✓</end> $k");
                    goto next;
                }
                // CHECK DIFF
                if (!$diff) {
                    if (!$this->mute) Mason::say("<green>✓</end> $k");
                    goto next;
                } else {
                    //print_r($diff);
                    //print_r($field_curr[$k]);
                    //print_r($v);
                }
            }
            // OTHER CHANGES
            $type = strtoupper(@$v['Type']);
            $null = ($v['Null'] == 'NO') ? "NOT NULL" : "NULL DEFAULT NULL";
            $extra = strtoupper(@$v['Extra']);
            // CREATE FIELD
            if (!@$field_curr[$k]) {
                $query = "ALTER TABLE `$table` ADD `$k` $type $null $extra $after;";
                $this->queries[] = $query;
                $this->queries_mini[] = false;
                $this->queries_color[] = 'green';
                if (!$this->mute) Mason::say("→ $query", 'green');
            }
            // UPDATE FIELD
            else {
                $query = "ALTER TABLE `$table` CHANGE `$k` `$k` $type $null $extra $after;";
                $this->queries[] = $query;
                $this->queries_mini[] = false;
                $this->queries_color[] = 'cyan';
                if (!$this->mute) Mason::say("→ $query", 'cyan');
            }
            // ADD PRIMARY KEY
            if (@$v['Key'] === 'PRI' and @$diff['Key'] === 'PRI') {
                $query = "ALTER TABLE `$table` ADD PRIMARY KEY(`$k`);";
                $this->queries[] = $query;
                $this->queries_mini[] = false;
                $this->queries_color[] = 'cyan';
                if (!$this->mute) Mason::say("→ $query", 'cyan');
                //$my->query($query);
                $this->actions++;
            }
            // ADD UNIQUE
            if (@$v['Key'] === 'UNI') {
                $query = "ALTER TABLE `$table` ADD UNIQUE(`$k`);";
                $this->queries[] = $query;
                $this->queries_mini[] = false;
                $this->queries_color[] = 'cyan';
                if (!$this->mute) Mason::say("→ $query", 'cyan');
                //$my->query($query);
                $this->actions++;
            }
            // ADD INDEX (NOT UNIQUE. FBUT AST SEARCH TOO.)
            if (@$v['Key'] === 'MUL') {
                $query = "ALTER TABLE `$table` ADD INDEX(`$k`);";
                $this->queries[] = $query;
                $this->queries_mini[] = false;
                $this->queries_color[] = 'cyan';
                if (!$this->mute) Mason::say("→ $query", 'cyan');
                //$my->query($query);
                $this->actions++;
            }

            //$my->query($query);
            $this->actions++;
            next:
            $after = "AFTER `$k`";
        }
        //ALTER TABLE `qmz_product` CHANGE `pro_name` `pro_name` VARCHAR(128) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;
        //ALTER TABLE `qmz_product` DROP `pro_status`;
        //ALTER TABLE `qmz_product` ADD `teste` INT(123) NULL AFTER `pro_status`;
        //ALTER TABLE `qmz_product` ADD `teste` INT(123) NULL AFTER `pro_status`;
        //ALTER TABLE `qmz_product` CHANGE `pro_status` `pro_status` VARCHAR(1) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;
    }
    //-------------------------------------------------------
    // CREATE TABLE : RUN QUERY
    //-------------------------------------------------------
    private function createTable($table, $field, $my)
    {
        global $_APP;
        if (!$this->mute) Mason::header("∴ $table", 'blue');
        $_comma = '';
        //
        $query = "";
        //$query .= "CREATE TABLE `{$_APP['MYSQL'][0]['NAME']}`.`$table` " . PHP_EOL;
        $query .= "CREATE TABLE `$table` " . PHP_EOL;
        $query .= "(" . PHP_EOL;
        foreach ($field as $k => $v) {

            // FIELD PARAMETERS
            $type = strtoupper(@$v['Type']);
            $null = ($v['Null'] == 'NO') ? "NOT NULL" : "NULL DEFAULT NULL";
            $extra = @strtoupper(@$v['Extra']);

            $query .= $_comma . "`$k` $type $null $extra";

            // SET PRIMARY KEY
            if (@$v['Key'] === 'PRI') $query .= ", PRIMARY KEY (`$k`)";

            // SET UNIQUE
            if (@$v['Key'] === 'UNI') $query .= ", UNIQUE (`$k`)";

            // SET UNIQUE
            if (@$v['Key'] === 'MUL') $query .= ", INDEX (`$k`)";

            $_comma = ', ' . PHP_EOL;
        }
        $query .= PHP_EOL . ")";
        $query .= PHP_EOL . "ENGINE = InnoDB;";
        if (!$this->mute) Mason::say("→ $query", 'green');
        //$my->query($query);
        $this->queries[] = $query;
        $this->queries_mini[] = "CREATE TABLE `$table` ...";
        $this->queries_color[] = 'green';
        $this->actions++;
    }
    //-------------------------------------------------------
    // DELETE TABLE : RUN QUERY
    //-------------------------------------------------------
    private function deleteTable($table, $my)
    {
        global $_APP;
        if (!$this->mute) Mason::header("∴ $table", 'blue');
        $query = "DROP TABLE $table";
        if (!$this->mute) Mason::say("→ $query", 'yellow');
        //$my->query($query);
        $this->queries[] = $query;
        $this->queries_mini[] = false;
        $this->queries_color[] = 'yellow';
        $this->actions++;
    }
    //-------------------------------------------------------
    // CREATE DB : RUN QUERY
    //-------------------------------------------------------
    private function createDatabase($name, $my)
    {
        $query = "CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
        $this->queries[] = $query;
        $this->queries_mini[] = "CREATE DATABASE `$name`";
        $this->queries_color[] = 'green';
        $this->actions++;
        $this->create_database_count++;
        if (!$this->mute) Mason::say("→ $query", 'green');
    }
}
