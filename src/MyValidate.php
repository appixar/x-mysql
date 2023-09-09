<?php
class MyValidate extends Controllers
{
    // RETURN ERROR
    public static function error($error, $data = [])
    {
        if (Novel::isAPI()) Http::die(400, $error);
        else return ['error' => $error, 'data' => $data];
    }
    //-------------------------------------
    // ID
    //-------------------------------------
    public static function validate_id($data)
    {
        return intval($data);
    }
    //-------------------------------------
    // STR (MAX 64)
    //-------------------------------------
    public static function validate_str($data)
    {
        //if (strlen($data) > 64) return self::error("String too long", $data);
        return $data;
    }
    //-------------------------------------
    // FLOAT
    //-------------------------------------
    public static function validate_float($data)
    {
        $comma = explode(",", $data);
        if (@$comma[1]) {
            $data = str_replace(".", "", $data);
            $data = str_replace(",", ".", $data);
        }
        return floatval($data);
    }
    // INT
    public static function validate_int($data)
    {
        return intval($data);
    }
    //-------------------------------------
    // UCWORDS (FNAME,LNAME)
    //-------------------------------------
    public static function validate_ucwords($data)
    {
        if (strlen($data) < 3) return self::error("Name too short", $data);
        return ucwords(low($data));
    }
    //-------------------------------------
    // ALPHANUMERIC (CODE)
    //-------------------------------------
    public static function validate_alphanumeric($data)
    {
        if (strlen($data) > 64) return self::error("String too long", $data);
        return alphanumeric($data);
    }
    //-------------------------------------
    // CHECK URL
    //-------------------------------------
    public static function validate_url($data)
    {
        // check string format
        $dots = explode(".", $data);
        if (!@$dots[1]) return self::error("Invalid url: $data", $data);
        $prefix = explode("http://", $data);
        if (!@$prefix[1]) $data = "http://$data";
        // return
        $data = str_replace(['"', "'"], '', $data);
        $data = addslashes(low($data));
        return $data;
    }
    //-------------------------------------
    // CHECK EMAIL
    //-------------------------------------
    public static function validate_email($data)
    {
        // check string format
        if (!validaMail($data)) return self::error("Invalid email format", $data);
        // check domain
        $domain = @explode("@", $data)[1];
        if (!checkdnsrr($domain, 'MX')) return self::error("Invalid domain: $domain", $data);
        $data = low($data);
        return $data;
    }
    //-------------------------------------
    // CHECK CPF
    //-------------------------------------
    public static function validate_cpf($data)
    {
        if (!validaCPF($data)) return self::error("Invalid CPF: $data", $data);
        $data = clean($data);
        return $data;
    }
    //-------------------------------------
    // CHECK CNPJ
    //-------------------------------------
    public static function validate_cnpj($data)
    {
        if (!validaCNPJ($data)) return self::error("Invalid CNPJ: $data", $data);
        $data = clean($data);
        return $data;
    }
    //-------------------------------------
    // DATE
    //-------------------------------------
    public static function validate_date($data)
    {
        // check str. size
        $dateSizeCheck = false;
        if (strlen($data) === 10 or strlen($data) === 19) $dateSizeCheck = true;
        if (!$dateSizeCheck) return self::error("Date invalid length", $data);
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
        return $data;
    }
    //-------------------------------------
    // PHONE
    //-------------------------------------
    public static function validate_phone($data)
    {
        $data = clean($data);
        if (strlen($data) !== 11) return self::error("Phone invalid length", $data);
        return $data;
    }
}
