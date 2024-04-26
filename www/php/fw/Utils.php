<?php
/*
Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2024 Oleg Savchuk www.osalabs.com
 */

class Utils {
    /**
     * split string by "whitespace characters" and return array
     * Example: $array = qw('one two three'); => array('one', 'two', 'three');
     * @param string $str space-char separated words
     * @return array      array of words or empty array
     */
    public static function qw($str) {
        if (is_array($str)) {
            return $str; #if array passed - don't chagne it
        }

        $str = trim($str);
        if ($str > "") {
            $arr = preg_split("/\s+/", $str);
            foreach ($arr as $key => $value) {
                $arr[$key] = str_replace('&nbsp;', ' ', $value);
            }
            return $arr;
        } else {
            return array();
        }
    }

    //convert from array back to qw-string
    //spaces converted to '&nbsp;'
    public static function qwRevert($arr) {
        $result = '';
        foreach ($arr as $key => $value) {
            $result .= str_replace(' ', '&nbsp;', $value) . ' ';
        }
        return $result;
    }

    /*
    convert string like "AAA|1 BBB|2 CCC|3 DDD" to hash
    (or just "AAA BBB CCC DDD")
    AAA => 1
    BBB => 2
    CCC => 3
    DDD => 1 (default value 1)

    WARN! replaces all "&nbsp;" to spaces (after convert)
     */
    public static function qh($str, $default_value = 1) {
        if (is_array($str)) {
            return $str; #if array passed - don't chagne it
        }

        $result = array();
        foreach (static::qw($str) as $value) {
            $kv  = explode('|', $value, 2);
            $val = $default_value;
            if (count($kv) == 2) {
                $val = str_replace('&nbsp;', ' ', $kv[1]);
            }

            $result[$kv[0]] = $val;
        }
        return $result;
    }

    public static function qhRevert($sh) {
        $result = array();
        foreach ($sh as $key => $value) {
            $result[] = str_replace(' ', '&nbsp;', $key) . '|' . $value;
        }
        return implode(' ', $result);
    }

    //get string with random chars A-Fa-f0-9
    public static function getRandStr($len) {
        $result = '';
        $chars  = array("A", "B", "C", "D", "E", "F", "a", "b", "c", "d", "e", "f", 0, 1, 2, 3, 4, 5, 6, 7, 8, 9);
        for ($i = 0; $i < $len; $i++) {
            $result .= $chars[mt_rand(0, count($chars) - 1)];
        }

        return $result;
    }

    //get icode with a given length based on a full set A-Za-z0-9
    //default length is 4
    public static function getIcode($len = 4) {
        $result = '';
        $chars  = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9);
        for ($i = ord('A'); $i <= ord('Z'); $i++) {
            $chars[] = chr($i);
        }

        for ($i = ord('a'); $i <= ord('z'); $i++) {
            $chars[] = chr($i);
        }

        for ($i = 0; $i < $len; $i++) {
            $result .= $chars[mt_rand(0, count($chars) - 1)];
        }

        return $result;
    }

    public static function urlescape($str) {
        return urlencode($str);
    }

    public static function n2br($str, $is_compress = '') {
        $res    = preg_replace("/\r/", "", $str);
        $regexp = "/\n/";
        if ($is_compress) {
            $regexp = "/\n+/";
        }

        return preg_replace($regexp, "<br>", $res);
    }

    public static function br2n($str) {
        return preg_replace("/<br>/i", "\n", $str);
    }

    public static function dehtml($str) {
        $aaa = preg_replace("/<[^>]*>/", "", $str);
        return preg_replace("/%%[^%]*%%/", "", $aaa); //remove special tags too
    }

    /**
     * for each row in $rows add array keys/values to this row
     * usage: Utils::arrayInject($this->list_rows, array('related_id' => $this->related_id));
     * @param array $rows array of assoc arrays
     * @param array $toadd keys/values to add
     * @return none, $rows changed by ref
     */
    public static function arrayInject(&$rows, $toadd) {
        foreach ($rows as $k => $row) {
            #array merge
            foreach ($toadd as $key => $value) {
                $rows[$k][$key] = $value;
            }
        }
    }

    /**
     * array of values to csv-formatted string for one line, order defiled by $fields
     * @param array $row hash values for csv line
     * @param array $fields plain array - field names
     * @return string one csv line with properly quoted values and "\n" at the end
     */
    public static function toCSVRow($row, $fields) {
        $result = '';

        foreach ($fields as $key => $fld) {
            $str = $row[$fld];
            if (preg_match('/[^\x20-\x7f]/', $str)) {
                //non-ascii data - convert to hex
                $str = bin2hex($str);
            }
            if (preg_match('/[",]/', $str)) {
                //quote string
                $str = '"' . str_replace('"', '""', nl2br($str)) . '"';
            }
            $result .= (($result) ? "," : "") . $str;
        }

        return $result . "\n";
    }

    //export $rows with $fields into csv format and echo to output
    public static function exportCSV($rows, $fields = '') {
        if (!is_array($fields)) {
            $fields = static::qh($fields);
        }

        #headers - if no fields set - read first row and get header names
        $headers_str = '';
        if (!count($fields)) {
            if (!count($rows)) {
                return "";
            }

            $fields        = array_keys($rows[0]);
            $fields_header = $fields;
        } else {
            $fields_header = array_values($fields);
            $fields        = array_keys($fields);
        }
        $headers_str = implode(',', $fields_header);

        echo $headers_str . "\n";
        foreach ($rows as $key => $row) {
            echo static::toCSVRow($row, $fields);
        }
    }

    /**
     * output response as csv
     * @var array $rows array of hashes from db_array
     * @var string|array $fields fields to export - string for qh or hash - (fieldname => field name in header), default - all export fields
     * @var string $filename human name of the file for browser, default "export.csv"
     */
    public static function responseCSV(array $rows, array|string $fields = '', string $filename = 'export.csv') {
        $filename = str_replace('"', "'", $filename); #quote filename

        header('Content-type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        static::exportCSV($rows, $fields);
    }

    public static function responseXLS(FW $fw, array $rows, array|string $fields = '', string $filename = 'export.xls'): void {
        $filename = str_replace('"', "'", $filename); #quote filename

        header('Content-type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        #TODO writeXLSExport
    }


    /**
     * zip multiple files into one
     * @param array $files {filename => filepath}
     * @param string $zip_file optional, filepath for zip archive, if empty - new created
     * @return string zip archive filepath
     */
    public static function zipFiles($files, $zip_file = '') {
        if (!$zip_file) {
            $zip_file = tempnam(sys_get_temp_dir(), 'osafw_');
        }

        $zip = new ZipArchive();
        if ($zip->open($zip_file, ZIPARCHIVE::OVERWRITE) !== TRUE) {
            throw new ApplicationException("Could not open zip archive [$zip_file]");
        }

        foreach ($files as $filename => $filepath) {
            $zip->addFile($filepath, $filename);
        }

        #close and save
        $zip->close();

        return $zip_file;
    }

    /**
     * bytes 2 human readable string
     * @param int $b bytes
     * @return string    string with KiB, MiB, GiB like:
     *
     * 123 - 123 b
     * 1234 - 1.24 KiB
     * 12345 - 12.35 KiB
     * 1234567 - 1.23 MiB
     * 1234567890 - 1.23 GiB
     */
    public static function bytes2str($b) {
        $result = $b;

        if ($b < 1024) {
            $result .= " B";
        } elseif ($b < 1048576) {
            $result = (ceil($b / 1024 * 100) / 100) . " KiB";
        } elseif ($b < 1073741824) {
            $result = (ceil($b / 1048576 * 100) / 100) . " MiB";
        } else {
            $result = (ceil($b / 1073741824 * 100) / 100) . " GiB";
        }

        return $result;
    }

    //return UUID v4, ex: 67700f72-57a4-4bc6-9c69-836e980390ce
    //WARNING: tries to use random_bytes or openssl_random_pseudo_bytes. If not present - pseudo-random data used
    public static function uuid() {
        if (function_exists('random_bytes')) {
            //PHP 7 only
            $data = random_bytes(16);
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            $data = openssl_random_pseudo_bytes(16);
        } else {
            $data = '';
            for ($i = 0; $i < 16; $i++) {
                $data .= chr(mt_rand(0, 255));
            }
        }

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * return path to tmp filename WITHOUT extension
     * @param string $prefix optional, default 'osafw_'
     * @return string         path
     */
    public static function getTmpFilename($prefix = 'osafw_') {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . self::uuid();
    }

    /**
     * for num (within total) and total - return string "+XXX%" or "-XXX%" depends if num is bigger or smaller than previous period (num-total)
     * @param float $num
     * @param float $total
     * @return string
     */
    public static function percentChange(float $num, float $total): string {
        $result = "";

        $prev_num = $total - $num;
        if ($prev_num == 0) {
            return ($num == 0) ? "0%" : "+100%";
        }

        $percent = (($num - $prev_num) / $prev_num) * 100;
        if ($percent >= 0) {
            $result = "+";
        }

        return $result . round($percent, 2) . "%";
    }


    /**
     * simple encrypt or decrypt a string with vector/key
     * @param string $action 'encrypt' or 'decrypt'
     * @param string $string string to encrypt or decrypt (base64 encoded)
     * @param string $v vector string
     * @param string $k key string
     * @return string         encrypted (base64 encoded) or decrypted string or FALSE if wrong action
     * TODO: use https://github.com/defuse/php-encryption instead
     */
    public static function crypt($action, $string, $v, $k) {
        $output         = false;
        $encrypt_method = "AES-256-CBC";

        // hash
        $key = hash('sha256', $k);

        // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
        $iv = substr(hash('sha256', $v), 0, 16);

        if ($action == 'encrypt') {
            $output = openssl_encrypt($string, $encrypt_method, $key, 0, $iv);
            $output = base64_encode($output);
        } elseif ($action == 'decrypt') {
            $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
        }

        return $output;
    }

    #simple encrypt/decrypt pwd based on config keys
    public static function encrypt($value) {
        return Utils::crypt('encrypt', $value, fw::i()->config->CRYPT_V, fw::i()->config->CRYPT_KEY);
    }

    public static function decrypt($value) {
        return Utils::crypt('decrypt', $value, fw::i()->config->CRYPT_V, fw::i()->config->CRYPT_KEY);
    }

    public static function jsonEncode($data) {
        return json_encode($data);
    }

    public static function jsonDecode($str) {
        if (is_null($str)) {
            return null; #Deprecated: json_decode(): Passing null to parameter #1 ($json) of type string is deprecated
        } else {
            return json_decode($str, true);
        }
    }

    /**
     * load content from url
     * @param string $url url to get info from
     * @param array $params optional, if set - post will be used, instead of get. Can be string or array
     * @param array $headers optional, add headers
     * @param string $to_file optional, save response to file (for file downloads)
     * @param array $curlinfo optional, return misc curl info by ref
     * @param bool $report_errors
     * @return string content received. FALSE if error
     */
    public static function loadURL($url, $params = null, $headers = null, $to_file = '', &$curlinfo = array(), $report_errors = true) {
        logger("CURL load from: [$url]", $params, $headers, $to_file);
        $cu = curl_init();

        curl_setopt($cu, CURLOPT_URL, $url);
        curl_setopt($cu, CURLOPT_TIMEOUT, 60);
        curl_setopt($cu, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($cu, CURLOPT_FAILONERROR, true); #cause fail on >=400 errors
        curl_setopt($cu, CURLOPT_FOLLOWLOCATION, true); #follow redirects
        curl_setopt($cu, CURLOPT_MAXREDIRS, 8); #max redirects
        if (is_array($headers)) {
            curl_setopt($cu, CURLOPT_HTTPHEADER, $headers);
        }

        if (!is_null($params)) {
            curl_setopt($cu, CURLOPT_POST, 1);
            curl_setopt($cu, CURLOPT_POSTFIELDS, $params);
        }
        if ($to_file > '') {
            #downloading to tmp file first
            $tmp_file   = $to_file . '.download';
            $fh_to_file = fopen($tmp_file, 'wb');
            curl_setopt($cu, CURLOPT_FILE, $fh_to_file);
            curl_setopt($cu, CURLOPT_TIMEOUT, 3600); #1h timeout
        }
        #curl_setopt($cu, CURLOPT_VERBOSE,true);
        ##curl_setopt($cu, CURLINFO_HEADER_OUT, 1);

        $result = curl_exec($cu);
        logger('TRACE', 'RESULT:', $result);
        $curlinfo = curl_getinfo($cu);
        #logger('TRACE', 'CURL INFO:', $curlinfo);
        if (curl_error($cu)) {
            $curlinfo['error'] = curl_error($cu);
            if ($report_errors) {
                logger('ERROR', 'CURL error: ' . curl_error($cu));
            }
            $result = false;
        }
        curl_close($cu);
        #logger("CURL RESULT:", $result);

        if ($to_file > '') {
            fclose($fh_to_file);
            #if file download successfull - rename to destination
            #if failed - just remove tmp file
            if ($result !== false) {
                rename($tmp_file, $to_file);
            } else {
                unlink($tmp_file);
            }
        }

        return $result;
    }

    #send file to URL with optional params using curl
    public static function sendFileToURL($url, $from_file, $params = null, $headers = null) {
        logger('TRACE', "CURL post file [$from_file] to: [$url]", $params, $headers);
        $cu = curl_init();

        curl_setopt($cu, CURLOPT_URL, $url);
        curl_setopt($cu, CURLOPT_POST, 1);
        curl_setopt($cu, CURLOPT_TIMEOUT, 3600); #1h timeout
        curl_setopt($cu, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($cu, CURLOPT_FAILONERROR, true); #cause fail on >=400 errors

        $headers1 = array(
            'Content-Type: multipart/form-data',
        );
        if (is_array($headers)) {
            $headers1 += $headers;
        }

        curl_setopt($cu, CURLOPT_HTTPHEADER, $headers1);

        $params1 = array(
            'file' => new CURLFile($from_file),
        );
        if (is_array($params)) {
            $params1 += $params;
        }

        curl_setopt($cu, CURLOPT_POSTFIELDS, $params1);

        #curl_setopt($cu, CURLOPT_VERBOSE,true);
        ##curl_setopt($cu, CURLINFO_HEADER_OUT, 1);

        $result = curl_exec($cu);
        #logger(curl_getinfo($cu));
        if (curl_error($cu)) {
            logger('ERORR', 'CURL error: ' . curl_error($cu));
            $result = false;
        }
        curl_close($cu);
        #logger("CURL RESULT:", $result);

        return $result;
    }

    /**
     * post json to the url
     * @param string $url url to get info from
     * @param array $json
     * @param array $to_file optional, save response to file (for file downloads)
     * @return array json data received. FALSE if error
     */
    public static function postJson($url, $json, $headers = [], $to_file = '') {
        $jsonstr = json_encode($json);

        $headers = array_merge(array(
            'Accept: application/json',
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonstr),
        ), $headers);

        $result = static::loadURL($url, $jsonstr, $headers, $to_file);
        if ($result !== false) {
            if ($to_file > '') {
                #if it was file transfer, just construct successful response
                $result = array(
                    'success' => true,
                    'fsize'   => filesize($to_file),
                );
            } else {
                $result = json_decode($result, true);
            }
        }
        return $result;
    }

    /**
     * GET json from the url
     * @param string $url url to get info from
     * @param string $to_file optional, save response to file (for file downloads)
     * @param array $headers optional, additional headers
     * @return array json data received. FALSE if error
     */
    public static function getJson($url, $to_file = '', $headers = null) {
        $headers2 = array(
            'Accept: application/json',
        );
        if (is_array($headers)) {
            $headers2 = array_merge($headers2, $headers);
        }

        $result = static::loadURL($url, null, $headers2, $to_file);
        if ($result !== false) {
            if ($to_file > '') {
                #if it was file transfer, just construct successful response
                $result = array(
                    'success' => true,
                    'fsize'   => filesize($to_file),
                );
            } else {
                $result = json_decode($result, true);
            }
        }
        return $result;
    }

    /**
     * return parsed json from the POST request
     * @return array json or FALSE
     */
    public static function getPostedJson(): array {
        $raw = file_get_contents("php://input");
        return json_decode($raw, true);
    }

    /**
     * split string by separator and returns exactly 2 values (if not enough values - empty strings added)
     * usage: list($path1, $path2) = Utils::split2('/', $path)
     * @param string $separator
     * @param string $str
     * @return array
     */
    public static function split2(string $separator, string $str): array {
        return array_pad(explode($separator, $str, 2), 2, '');
    }

    /**
     * capitalize string:
     *  - if mode='all' - capitalize all words
     *  - otherwise - just a first word
     * EXAMPLE: mode="" : sample string => Sample string
     *          mode="all" : sample STRING => Sample String
     * @param string $str
     * @param string $mode
     * @return string
     */
    public static function capitalize(string $str, string $mode = ""): string {
        if ($mode == "all") {
            return ucwords(strtolower($str));
        } else {
            return ucfirst(strtolower($str));
        }
    }

    /**
     * convert/normalize external table/field name to fw standard name
     * "SomeCrazy/Name" => "some_crazy_name"
     * @param string $str
     * @return string
     */
    public static function name2fw(string $str): string {
        $result = $str;
        $result = preg_replace('/^tbl|dbo/i', '', $result); // remove tbl,dbo prefixes if any
        $result = preg_replace('/([A-Z]+)/', '_$1', $result); // split CamelCase to underscore, but keep abbrs together ZIP/Code -> zip_code
        $result = preg_replace('/\W+/', '_', $result); // replace all non-alphanum to underscore
        $result = preg_replace('/_+/', '_', $result); // deduplicate underscore
        $result = trim($result, " _"); // remove first and last _ if any
        $result = strtolower($result); // and finally to lowercase
        return $result;
    }

    /**
     * convert some system name to human-friendly name'
     * "system_name_id" => "System Name ID"
     * @param string $str
     * @return string
     */
    public static function name2human(string $str): string {
        $str_lc = strtolower($str);
        if ($str_lc == "icode") {
            return "Code";
        }
        if ($str_lc == "iname") {
            return "Name";
        }
        if ($str_lc == "idesc") {
            return "Description";
        }
        if ($str_lc == "idate") {
            return "Date";
        }
        if ($str_lc == "itype") {
            return "Type";
        }
        if ($str_lc == "iyear") {
            return "Year";
        }
        if ($str_lc == "id") {
            return "ID";
        }
        if ($str_lc == "fname") {
            return "First Name";
        }
        if ($str_lc == "lname") {
            return "Last Name";
        }
        if ($str_lc == "midname") {
            return "Middle Name";
        }

        $result = $str;
        $result = preg_replace('/^tbl|dbo/i', '', $result); // remove tbl prefix if any
        $result = preg_replace('/_+/', ' ', $result); // underscores to spaces
        $result = preg_replace('/([a-z ])([A-Z]+)/', '$1 $2', $result); // split CamelCase words
        $result = preg_replace('/ +/', ' ', $result); // deduplicate spaces
        $result = static::capitalize($result, 'all'); // Title Case
        $result = trim($result);

        if (preg_match('/\bid\b/i', $result)) {
            // if contains id/ID - remove it and make singular
            $result = preg_replace('/\s*\bid\b/i', '', $result);
            // singularize TODO use external lib to handle all cases
            $result = preg_replace('/(\S)(?:ies)\s*$/', '$1y', $result); // -ies -> -y
            $result = preg_replace('/(\S)(?:es)\s*$/', '$1e', $result); // -es -> -e
            $result = preg_replace('/(\S)(?:s)\s*$/', '$1', $result); // remove -s at the end
        }

        $result = trim($result);
        return $result;
    }

    /**
     * convert c/snake style name to CamelCase
     * system_name => SystemName
     * @param string $str
     * @return string
     */
    public static function nameCamelCase(string $str): string {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $str)));
    }

}
