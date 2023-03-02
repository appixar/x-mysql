<?php
class MyService
{
    public $con = array();
    public $error = false;
    public $die = true; // die after query errors
    /*
    $conf = array(
        'die' => true,
        'id' => 0, // $_APP[MYSQL][0]
    );
    */
    public function __construct($conf = array())
    {
        global $_APP;
        if (!@$_APP["MYSQL"]) die('MYSQL CONFIG IS MISSING' . PHP_EOL);

        // Die after query errors?
        if (isset($conf['die'])) $this->die = $conf['die'];

        // Default connection ID = first
        $con_id = @$conf['id'];
        if (!$con_id) {
            foreach ($_APP["MYSQL"] as $k => $v) {
                $con_id = $k;
                break;
            }
        }

        // Connection data
        $my = @$_APP["MYSQL"][$con_id];
        if (!$my) die("MYSQL NOT FOUND: $con_id" . PHP_EOL);

        // Wildcard variable replacement
        if (@$conf['wildcard']) {
            foreach ($my as $k => $v) {
                $my[$k] = str_replace('%', $conf['wildcard'], $v);
            }
        }
        // Connect
        try {
            $dsn = "mysql:host={$my['HOST']};dbname={$my['NAME']};port={$my['PORT']};charset=utf8";
            $this->con = new PDO($dsn, $my['USER'], $my['PASS'], array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
            //$dsn = "mysql:host={$my['HOST']};dbname={$my['NAME']};port={$my['PORT']}";
            //$this->con = new PDO($dsn, $my['USER'], $my['PASS']);
        } catch (PDOException $e) {
            die("Error: " . $e->getMessage() . PHP_EOL);
        }
    }
    public function query($query, $variables = array())
    {
        $stmt = $this->con->prepare($query);

        if ($variables) {
            // Map used keys (bugfix)
            $keys_used = array();
            $keys_find = explode(":", $query);
            foreach ($keys_find as $k => $v) {
                $v = explode(" ", $v)[0];
                $keys_used[$v] = 1;
            }
            // Bind only used keys (bugfix)
            foreach ($variables as $k => $v) {
                if (@$keys_used[$k]) $stmt->bindValue(":$k", $v);
            }
        }
        if (!$stmt->execute()) {
            if ($this->die) die($stmt->errorInfo()[2]);
            $this->error = $stmt->errorInfo()[2]; // 2 = text
            return false;
        }
        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $res;
    }
    public function insert($table, $data = array())
    {
        // VARIABLES DO BIND
        $binds = array();

        // BUILD QUERY
        $col = $val = $comma = "";
        foreach ($data as $k => $v) {
            // fix data
            if ($v == "NULL" or $v == "null") $v = "NULL"; // null
            elseif ($v == '') $v = "NULL"; // blank
            elseif (is_numeric($v)) $v = "'$v'"; // blank
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
            if ($this->die) die($stmt->errorInfo()[2]);
            $this->error = $stmt->errorInfo()[2]; // 2 = text
            return false;
        }
        $id = $this->con->lastInsertId();
        return $id;
    }
    public function update($table, $data = array(), $condition = array())
    {
        // VARIABLES DO BIND
        $binds = array();

        // BUILD QUERY
        $comma = $values = $and = $where = $and = "";
        foreach ($data as $k => $v) {
            // fix data
            if ($v == "NULL" or $v == "null") $v = "NULL";
            elseif ($v == "") $v = "NULL";
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
            if ($this->die) die($stmt->errorInfo()[2]);
            $this->error = $stmt->errorInfo()[2]; // 2 = text
            return false;
        }
        return $stmt->rowCount();
    }
}
