<?php
function blau() {
    echo 'bleuu';
}
// ======================================
// MYSQLI FUNCTIONS
//    PROCEDURE MODE
// ======================================
function jwconex($i = 0)
{
    global $jwconex, $_APP;
    $c = $_APP["MYSQL"][$i];
    $start = microtime(true); // inicia cronômetro
    new Debug(__FUNCTION__, "(#$i) connecting to {$c['HOST']} {$c['PORT']} ({$c['NAME']})...", "sql_conex");
    $jwconex[$i] = @mysqli_connect($c['HOST'], $c['USER'], $c['PASS'], $c['NAME'], $c['PORT']);
    if (mysqli_connect_error()) {
        new Debug(__FUNCTION__, mysqli_connect_error(), "error");
        Arion::err("DB ERROR = CAN'T CONNECT.", mysqli_connect_error());
    } else {
        $time_elapsed_secs = number_format((microtime(true) - $start), 4);
        new Debug(__FUNCTION__, "(#$i) connected in $time_elapsed_secs s", "sql_conex");
    }
}
// SQL INJECTION PROTECT
function jwsafe($i = 0)
{
    return false;
    global $jwconex, $_APP, $_POST, $_GET, $_REQUEST;
    if (!$_POST) {
        return false;
    }
    $c = $_APP["MYSQL"][$i];
    if ($c['HOST'] == "" or $c['USER'] == "" or $c['PASS'] == "") {
        return false;
    }
    if (!$jwconex[$i]) {
        jwconex($i);
    }
    foreach ($_REQUEST as $k => $v) {
        //$_REQUEST[$k] = $jwconex[$i]->real_escape_string($v);
        $_REQUEST[$k] = mysqli_real_escape_string($jwconex[$i], $v);
    }
    foreach ($_POST as $k => $v) {
        $_POST[$k] = mysqli_real_escape_string($jwconex[$i], $v);
    }
    foreach ($_GET as $k => $v) {
        $_GET[$k] = mysqli_real_escape_string($jwconex[$i], $v);
    }
}

function jwquery($query, $i = 0)
{
    if (!$query or $query == "") {
        return false;
    }
    global $jwconex;
    if (!isset($jwconex[$i])) {
        jwconex($i);
    }
    $data = [];
    if ($jwconex[$i]) {
        $start = microtime(true);
        new Debug(__FUNCTION__, "(#$i) $query", "sql_sel");
        $rs = mysqli_query($jwconex[$i], $query) or new Debug(__FUNCTION__, mysqli_error($jwconex[$i]), "error");
        if (!$rs) {
            Arion::err("DB ERROR = BAD QUERY.", mysqli_error($jwconex[$i]) . PHP_EOL . "-" . PHP_EOL . $query);
            #die(mysqli_error($jwconex[$i]));
        }
        $rows_aff = mysqli_affected_rows($jwconex[$i]);
        $time_elapsed_secs = number_format((microtime(true) - $start), 4);
        new Debug(__FUNCTION__, "$rows_aff rows affected in $time_elapsed_secs s", "sql_sel");
    }
    if (isset($rs)) {
        if (is_bool($rs)) {
            return $rs;
        } else {
            while ($row = $rs->fetch_assoc()) {
                $data[] = $row;
            }
        }
        return $data;
    }
}

function jwinsert($table_name, $array_data, $i = 0)
{
    global $jwconex;
    if (!isset($jwconex[$i])) {
        jwconex($i);
    }
    $count = 0; // fix virgula
    $col = $val = "";
    foreach ($array_data as $k => $v) {
        //if ($v <> "") {
        if ($count > 0) {
            $val .= ", ";
            $col .= ", ";
        }
        $col .= "`$k`";
        if ($v == "NULL") {
            $val .= "NULL";
        } // null
        elseif ($v != "") {
            $val .= "'$v'";
        } // content
        else {
            $val .= "''";
        } // empty str
        $count++;
        //}
    }
    $qr = "INSERT INTO `$table_name` ($col) VALUES ($val)";
    //echo $qr; exit;

    $start = microtime(true);
    new Debug(__FUNCTION__, "(#$i) $qr", "sql_ins");

    $rs = mysqli_query($jwconex[$i], $qr) or new Debug(__FUNCTION__, mysqli_error($jwconex[$i]), "error");
    if (!$rs) {
        #die(mysqli_error($jwconex[$i]));
        Arion::err("DB ERROR = BAD INSERT.", mysqli_error($jwconex[$i]) . "<p>$qr</p>");
    }

    $rows_aff = mysqli_affected_rows($jwconex[$i]);
    $time_elapsed_secs = number_format((microtime(true) - $start), 4);
    new Debug(__FUNCTION__, "$rows_aff rows affected in $time_elapsed_secs s", "sql_ins");

    $id = mysqli_insert_id($jwconex[$i]);
    return $id;
}

function jwupdate($table_name, $array_data, $array_condition, $i = 0)
{
    global $jwconex;
    if (!isset($jwconex[$i])) {
        jwconex($i);
    }
    $virg = $val = $and = $where = "";
    foreach ($array_data as $k => $v) {
        if ($v != "NULL") $v = "'$v'";
        $val .= "$virg`$k`=$v";
        $virg = ",";
    }
    if (is_array($array_condition)) {
        foreach ($array_condition as $k => $v) {
            if ($v != "") {
                //$v = mysqli::real_escape_string($v);
                $where .= $and . "`$k`='$v'";
            } else {
                $where .= $and . "`$k` IS NULL";
            }
            $and = " AND ";
        }
    } else {
        $where = $array_condition;
    }
    $qr = "UPDATE `$table_name` SET $val WHERE $where";

    $start = microtime(true);
    new Debug(__FUNCTION__, "(#$i) $qr", "sql_upd");

    $rs = mysqli_query($jwconex[$i], $qr) or new Debug(__FUNCTION__, mysqli_error($jwconex[$i]), "error");
    if (!$rs) {
        die(mysqli_error($jwconex[$i]));
    }
    $rows_aff = mysqli_affected_rows($jwconex[$i]);
    $time_elapsed_secs = number_format((microtime(true) - $start), 4);
    new Debug(__FUNCTION__, "$rows_aff rows affected in $time_elapsed_secs s", "sql_upd");
    return $rows_aff;
}

