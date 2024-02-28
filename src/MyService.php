<?php
class MyService extends Services
{
    public $con = array();
    public $conf = [];
    public $error = false;
    public $die = true; // die after query errors
    private static $instances = [];
    /*
    $conf = array(
        'die' => true,
        'id' => 0, // $_APP_VAULT[MYSQL][0]
    );
    */
    public function __construct($conf = array())
    {
        $this->conf = $conf;
    }
    public function connect()
    {
        global $_APP_VAULT, $_ENV;
        if (!@$_APP_VAULT["MYSQL"]) Novel::refreshError('Mysql error', 'Mysql config is missing');

        // Get conf
        $conf = $this->conf;

        // Die after query errors?
        if (isset($conf['die'])) $this->die = $conf['die'];

        // Default connection ID = first
        $con_id = @$conf['db_key'];
        if (!$con_id) {
            foreach ($_APP_VAULT["MYSQL"] as $k => $v) {
                $con_id = $k;
                break;
            }
        }

        // Connection data
        $my = @$_APP_VAULT["MYSQL"][$con_id];
        if (!$my) Novel::refreshError('Mysql error', "Conn ID not found: $con_id");

        // Replace with env variables
        foreach ($my as $k => $v) {
            // value is between <> ?
            if (!is_array($v) and substr($v, 0, 1) === '<' and substr($v, -1) === '>') {
                $v = substr($v, 1, -1); // remove <>
                if (@!$_ENV[$v]) Novel::refreshError('Mysql error', "'$v' not found in .env");
                $my[$k] = $_ENV[$v];
            }
        }

        // Wildcard variable replacement
        if (@$conf['tenant_key']) {
            foreach ($my as $k => $v) {
                $my[$k] = str_replace('<TENANT_KEY>', $conf['tenant_key'], $v);
            }
        }

        // Dont select database? (create if not exists after)
        $dbName = '';
        if (@!$conf['ignore-database']) $dbName = "dbname={$my['NAME']};";

        // Identificador único para a configuração de conexão
        $uniqueId = md5(serialize($my));

        // Verifica se já existe uma instância com a mesma configuração
        if (isset(self::$instances[$uniqueId])) {
            return self::$instances[$uniqueId];
        }

        // Connect
        try {
            // Cria a nova instância da conexão PDO
            $dsn = "mysql:host={$my['HOST']};{$dbName}port={$my['PORT']};charset=utf8mb4";
            $con = new PDO($dsn, $my['USER'], $my['PASS'], array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"));
            // Armazena a instância na propriedade estática
            self::$instances[$uniqueId] = $con;
            return $con;
        } catch (PDOException $e) {
            Novel::refreshError('Mysql error', $e->getMessage());
        }
    }
    public function query($query, $variables = array())
    {
        if (!$this->con) $this->con = $this->connect();
        $stmt = $this->con->prepare($query);

        if ($variables) {
            // Map used keys (bugfix)
            $keys_find = explode(":", $query);
            unset($keys_find[0]);
            array_values($keys_find);
            foreach ($keys_find as $key) {
                $key = explode(" ", $key)[0];
                if (!is_numeric($key) and isAlphanumericOrUnderscore($key)) { // date dots bugfix
                    if (@$variables[$key]) $stmt->bindValue(":$key", $variables[$key]);
                    else Novel::refreshError('Mysql error', "Bind key not found ':$key'");
                }
            }
        }
        if (!$stmt->execute()) {
            if ($this->die) Novel::refreshError('Mysql error', $stmt->errorInfo()[2]);
            $this->error = $stmt->errorInfo()[2]; // 2 = text
            return false;
        }
        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $res;
    }
    public function insert($table, $data = array())
    {
        // CONNECT
        if (!$this->con) $this->con = $this->connect();

        // VARIABLES DO BIND
        $binds = array();

        // BUILD QUERY
        $col = $val = $comma = "";
        foreach ($data as $k => $v) {
            // fix data
            if ($v === "NULL" or $v === "null" or $v === '') $v = "NULL"; // null
            elseif (is_numeric($v)) $v = "$v"; // blank
            else {
                $binds[$k] = $v;
                $v = ":$k"; // content
            }
            // populate
            $val .= "$comma$v";
            $col .= "$comma`$k`";
            // comma
            $comma = ",";
        }
        $query = "INSERT INTO `$table` ($col) VALUES ($val)";

        // PREPARE QUERY
        $stmt = $this->con->prepare($query);

        // BIND VALUES
        foreach ($binds as $k => $v) $stmt->bindValue(":$k", $v);

        // RUN QUERY
        if (!$stmt->execute()) {
            if ($this->die) Novel::refreshError('Mysql error', $stmt->errorInfo()[2]);
            $this->error = $stmt->errorInfo()[2]; // 2 = text
            return false;
        }
        $id = $this->con->lastInsertId();
        return $id;
    }
    public function update($table, $data = array(), $condition = array())
    {
        // CONNECT
        if (!$this->con) $this->con = $this->connect();

        // VARIABLES DO BIND
        $binds = array();

        // BUILD QUERY
        $comma = $values = $and = $where = $and = "";
        foreach ($data as $k => $v) {
            // fix data
            if ($v === "NULL" or $v === "null") $v = "NULL";
            elseif (is_numeric($v)) $v = "$v"; // blank
            elseif ($v === "") $v = "NULL";
            else {
                $binds[$k] = $v;
                $v = ":$k";
            }
            // populate
            $values .= "$comma`$k`=$v";
            // comma
            $comma = ",";
        }

        // BUILD CONDITION
        if (is_array($condition)) {
            foreach ($condition as $k => $v) {
                // fix data
                if ($v === "NULL") $where .= $and . "`$k` IS NULL";
                elseif ($v === "") $where .= $and . "`$k` = ''";
                elseif (is_numeric($v)) $where .= $and . "`$k` = '$v'";
                else {
                    $where .= $and . "`$k` = :$k";
                    $binds[$k] = $v;
                }
                $and = " AND ";
            }
        } else $where = $condition;

        // RUN QUERY
        $query = "UPDATE `$table` SET $values WHERE $where";
        //echo $query . PHP_EOL;
        //die($query);

        // PREPARE QUERY
        $stmt = $this->con->prepare($query);

        // BIND VALUES
        //pre($binds);
        foreach ($binds as $k => $v) $stmt->bindValue(":$k", $v);

        // RUN QUERY
        if (!$stmt->execute()) {
            if ($this->die) Novel::refreshError('Mysql error', $stmt->errorInfo()[2]);
            $this->error = $stmt->errorInfo()[2]; // 2 = text
            return false;
        }
        return $stmt->rowCount();
    }
    public static function getAllFields()
    {
        $databasePaths = Novel::findPathsByType("database");
        $fields = [];
        foreach ($databasePaths as $path) {
            if (file_exists($path) and is_dir($path)) {
                $table_files = scandir($path);
                foreach ($table_files as $fn) {
                    $fp = "$path/$fn";
                    if (is_file($fp)) {
                        // new fields
                        $data = @yaml_parse(file_get_contents($fp));
                        // MULTIPLE TABLES ON SINGLE FILE?
                        if (!is_array($data)) continue;
                        foreach ($data as $table_name => $table_cols) {
                            foreach ($table_cols as $table_name => $table_param) {
                                $fields[$table_name] = $table_param;
                            }
                        }
                    }
                }
            }
        }
        return $fields;
    }
    // VALIDATE INPUT FIELDS
    public static function sanitize($receivedData, $requiredFields = false)
    {
        global $_APP;

        // CHECK REQUIRED FIELDS
        if ($requiredFields) {
            if (is_array($requiredFields)) $fields = $requiredFields;
            else $fields = explode(",", $requiredFields);
            foreach ($fields as $field) {
                $field = trim($field);
                if (!@$receivedData[$field]) Http::die(400, "Missing required field: $field");
            }
        }

        // SANITIZE FIELDS
        if (!$receivedData) return true;
        Novel::load('MyValidate');
        $validatedData = $receivedData;

        // LOOP IN ALL DB FIELDS
        $fields = MyService::getAllFields();
        foreach ($receivedData as $fieldName => $fieldValue) {
            // IS NOT DB FIELD
            if (!@$fields[$fieldName]) {
                $validatedData[$fieldName] = $fieldValue;
                continue;
            }
            if (!$fieldValue) continue;
            $params = explode(" ", $fields[$fieldName]);
            foreach ($params as $param) {
                $methodName = "validate_$param";
                if (method_exists('MyValidate', $methodName)) {
                    //echo "$methodName=> $fieldName=>" . MyValidate::$methodName($fieldValue) . "<br/>";
                    $validatedData[$fieldName] = MyValidate::$methodName($fieldValue);
                } elseif (function_exists($param)) {
                    $validatedData[$fieldName] = $param($fieldValue);
                }
                // validate error?
                if (@!empty($validatedData[$fieldName]['error'])) {
                    $validatedData['error'] = @$validatedData[$fieldName]['error'];
                    $validatedData['errors'][$fieldName] = @$validatedData[$fieldName]['error'];
                }
            }
        }
        return $validatedData;
    }
    // VALIDATE & TRANSFORM DATA 
    private static function validateSpecial($data, $type, $fieldName)
    {
        switch ($type) {
                //-------------------------------------
                // CHECK EMAIL
                //-------------------------------------
            case "email":
                // check string format
                if (!filter_var($data, FILTER_VALIDATE_EMAIL)) Http::die(400, "Invalid $type: $data");
                // check domain
                $domain = explode("@", $data)[1];
                if (!checkdnsrr($domain, 'MX')) Http::die(400, "Invalid domain: $data");
                $data = low($data);
                break;
                //-------------------------------------
                // CHECK CPF
                //-------------------------------------
            case "cpf":
                if (!validaCPF($data)) Http::die(400, "Invalid $type: $data");
                $data = clean($data);
                break;
                //-------------------------------------
                // UCWORDS (FNAME, LNAME)
                //-------------------------------------
            case "ucwords":
                if (strlen($data) < 3) Http::die(400, "$fieldName is too short");
                $data = ucwords(low($data));
                break;
                //-------------------------------------
                // DATE
                //-------------------------------------
            case "date":
                // check str. size
                $dateSizeCheck = false;
                if (strlen($data) === 10 or strlen($data) === 19) $dateSizeCheck = true;
                if (!$dateSizeCheck) Http::die(400, "$fieldName invalid lenght");
                // separate date
                $date = explode(' ', $data)[0];
                // time?
                $time = '00:00:00';
                if (@explode(' ', $data)[1] and @explode(':', $data)[1]) $time = explode(' ', $data)[1];
                // format br?
                if (@explode('/', $data)[1]) {
                    $date = @explode('/', $date)[2] . '-' . @explode('/', $date)[1] . '-' . @explode('/', $date)[0];
                }
                // append time
                $data = "$date $time";
                break;
                //-------------------------------------
                // PHONE
                //-------------------------------------
            case "phone":
                $data = clean($data);
                if (strlen($data) !== 11) Http::die(400, "$fieldName invalid lenght");
                break;
                //-------------------------------------
                // CPF
                //-------------------------------------
            case "phone":
                $data = clean($data);
                if (strlen($data) !== 11) Http::die(400, "$fieldName invalid lenght");
                break;
        }
        return $data;
    }
}
