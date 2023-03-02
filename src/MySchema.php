<?php

class MySchema extends Arion
{
    // queries
    public $queries = array();
    public $queries_mini = array();
    public $queries_color = array();
    // sub arguments
    public $mute = false;
    // util
    private $actions = 0;
    private $schema_default = array(
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
    );

    public function __construct()
    {
        if (!is_writable(self::DIR_SCHEMA)) {
            die('<pre>/database/schema is not writeable.');
        }
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

        foreach ($_APP['MYSQL'] as $mysql_key => $mysql) {
            /* PATH DEPRECATED
            if (!@$mysql['PATH']) {
                Mason::say("✗ MySQL '$mysql_key' don´t have a PATH.", true, 'red');
                goto next_db;
            }*/
            Mason::say("► MySQL '$mysql_key' ...", true, 'cyan');

            //$path = __DIR__ . '/../../' . $mysql['PATH'] . '/';
            $databasePaths = Arion::findPathsByType("database");

            // MYSQL KEY HAVE AN WILDCARD?
            $wild = false;
            if (strpos($mysql['NAME'], '%') or strpos($mysql['HOST'], '%') or strpos($mysql['USER'], '%')) {
                $wild = true;
            }
            // WILDCARD EXISTS
            if ($wild) {
                // MISSING WILDCARD CONFIG
                if (!@isset($mysql['WILD']['KEY']) or !@$mysql['WILD']['TABLE'] or !@$mysql['WILD']['FIELD'] or !@$mysql['WILD']['WHERE']) {
                    Mason::say("✗ Missing wildcard parameters", false, 'red');
                    goto next_db;
                }
                if (!@$_APP['MYSQL'][$mysql['WILD']['KEY']]) {
                    Mason::say("✗ MySQL '{$mysql['WILD']['KEY']}' not found. Can't build wildcard loop.", false, 'red');
                    goto next_db;
                }
                // CREATE WILDCARD LOOP
                $my_temp = new my(['id' => $mysql['WILD']['KEY']]);
                $wild_res = $my_temp->query("SELECT {$mysql['WILD']['FIELD']} FROM {$mysql['WILD']['TABLE']} WHERE {$mysql['WILD']['WHERE']}");
                $wild_loop = array();
                $x = 0;
                for ($i = 0; $i < @count($wild_res); $i++) {
                    $wild_value = $wild_res[$i][$mysql['WILD']['FIELD']];
                    $wild_loop[$x] = $mysql;
                    $wild_loop[$x]['WILDCARD_VALUE'] = $wild_value;
                    $wild_loop[$x]['NAME'] = str_replace('%', $wild_value, $mysql['NAME']);
                    $wild_loop[$x]['HOST'] = str_replace('%', $wild_value, $mysql['HOST']);
                    $wild_loop[$x]['USER'] = str_replace('%', $wild_value, $mysql['USER']);
                    $x++;
                }
            }
            // DONT EXISTS WILDCARD LOOP
            else {
                $wild_loop[0] = $mysql;
            }
            //--------------------------------------------
            //
            // WILDCARD LOOP
            //
            //--------------------------------------------
            //for ($y = 0; $y < count($wild_loop); $y++) {
            foreach ($wild_loop as $db) {

                // RESET DEBUG DATA
                $this->queries = array();
                $this->queries_mini = array();
                $this->queries_color = array();
                $this->actions = 0;

                // CONNECT
                $my_conf = ['id' => $mysql_key];
                $wildcard = '';
                if ($wild) {
                    $wildcard = $db['WILDCARD_VALUE'];
                    $my_conf['wildcard'] = $wildcard;
                    Mason::say("→ Start $mysql_key/$wildcard", true, 'header');
                }

                $my = new my($my_conf);

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

                                Mason::say("");
                                Mason::say("⍐ Processing: " . realpath($fp), false, 'magenta');

                                // CHECK YML FILE INTEGRITY
                                //if (!$this->checkFileIntegrity($fp)) goto nextFile;

                                // new fields
                                $data = @yaml_parse(file_get_contents($fp));

                                // MULTIPLE TABLES ON SINGLE FILE?
                                if (!is_array($data)) {
                                    Mason::say("* Invalid file format. Ignored.", false, 'yellow');
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
                                        Mason::say("* Invalid file format. Ignored.", false, 'yellow');
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

                    // DELETE TABLES THAT ARE NOT IN /SCHEMA
                    foreach ($tables_real as $k) {
                        if (!in_array($k, $tables_new)) $this->deleteTable($k, $my);
                    }
                }

                // CONFIRM CHANGES
                if (!empty($this->queries)) {
                    Mason::say("");
                    Mason::say("→ {$this->actions} requested actions...");
                    Mason::say("→ Please, verify:");
                    Mason::say("");
                    for ($z = 0; $z < count($this->queries); $z++) {
                        if ($this->queries_mini[$z]) $qr = $this->queries_mini[$z];
                        else $qr = $this->queries[$z];
                        Mason::say("→ $qr", false, $this->queries_color[$z]);
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
                        goto next_wild;
                    }
                    //----------------------------------------------
                    // RUN QUERIES!
                    //----------------------------------------------
                    for ($z = 0; $z < count($this->queries); $z++) {
                        $my->query($this->queries[$z]);
                    }
                } // CONFIRM 
                Mason::say("❤ Finished $mysql_key/$wildcard. Changes: {$this->actions}", true, 'header');
                next_wild:
            }
            next_db:
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
            $type_real = explode("(", @$this->schema_default[$type]['Type'])[0];

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
            // field unique
            $uni = array_search('unique', explode(" ", $v));
            if ($uni !== false) $key = "UNI";
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
        if (!$this->mute) Mason::say("∴ $table", true, 'blue');
        $query = '';

        // REMOVE FIELDS
        foreach ($field_curr as $k => $v) {
            if (!@$field[$k]) {
                $query = "ALTER TABLE `$table` DROP `$k`;";
                $this->queries[] = $query;
                $this->queries_mini[] = false;
                $this->queries_color[] = 'yellow';
                if (!$this->mute) Mason::say("→ $query", false, 'yellow');
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

            // ADD PRIMARY KEY
            if (@$v['Key'] === 'PRI' and @$diff['Key'] === 'PRI') {
                $query = "ALTER TABLE `$table` ADD PRIMARY KEY(`$k`);";
                $this->queries[] = $query;
                $this->queries_mini[] = false;
                $this->queries_color[] = 'cyan';
                if (!$this->mute) Mason::say("→ $query", false, 'cyan');
                //$my->query($query);
                $this->actions++;
            }
            // ADD UNIQUE
            if (@$v['Key'] === 'UNI') {
                $query = "ALTER TABLE `$table` ADD UNIQUE(`$k`);";
                $this->queries[] = $query;
                $this->queries_mini[] = false;
                $this->queries_color[] = 'cyan';
                if (!$this->mute) Mason::say("→ $query", false, 'cyan');
                //$my->query($query);
                $this->actions++;
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
                if (!$this->mute) Mason::say("→ $query", false, 'green');
            }
            // UPDATE FIELD
            else {
                $query = "ALTER TABLE `$table` CHANGE `$k` `$k` $type $null $extra $after;";
                $this->queries[] = $query;
                $this->queries_mini[] = false;
                $this->queries_color[] = 'cyan';
                if (!$this->mute) Mason::say("→ $query", false, 'cyan');
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
        if (!$this->mute) Mason::say("∴ $table", true, 'blue');
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
            $extra = strtoupper(@$v['Extra']);

            $query .= $_comma . "`$k` $type $null $extra";

            // SET PRIMARY KEY
            if (@$v['Key'] === 'PRI') $query .= ", PRIMARY KEY (`$k`)";

            // SET UNIQUE
            if (@$v['Key'] === 'UNI') $query .= ", UNIQUE (`$k`)";

            $_comma = ', ' . PHP_EOL;
        }
        $query .= PHP_EOL . ")";
        $query .= PHP_EOL . "ENGINE = InnoDB;";
        if (!$this->mute) Mason::say("→ $query", false, 'green');
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
        if (!$this->mute) Mason::say("∴ $table", true, 'blue');
        $query = "DROP TABLE $table";
        if (!$this->mute) Mason::say("→ $query", false, 'yellow');
        //$my->query($query);
        $this->queries[] = $query;
        $this->queries_mini[] = false;
        $this->queries_color[] = 'yellow';
        $this->actions++;
    }
}
