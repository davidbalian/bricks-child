<?php
/** Standalone provider-boundary tests. Stubs only: never loads WordPress, sends SMS or calls HTTP. */
namespace Twilio\Rest {
    final class Client {
        public object $verify;
        public function __construct(...$ignored) { $this->verify = new Node(); }
    }
    final class Node {
        public function __get($name) { return new self(); }
        public function services($service) { $GLOBALS['provider_service'] = $service; return $this; }
        public function create($input, $channel = null) {
            $GLOBALS['provider_calls'][] = [$input,$channel];
            if ($GLOBALS['provider_throw']) { throw new \RuntimeException('Sensitive upstream detail must not escape'); }
            return (object) ['sid'=>'VE'.str_repeat('a',32),'status'=>$GLOBALS['provider_status']];
        }
    }
}
namespace AutoAgora\Dealer { final class Connect { public static function enabled(): bool { return true; } } }
namespace {
    if (PHP_SAPI !== 'cli') { exit; }
    define('ABSPATH', '/not-a-wordpress-installation/');
    foreach (['AUTOAGORA_CONNECT_SIGNUP_ENABLED'=>true,'TURNSTILE_SITE_KEY'=>'dummy-site','TURNSTILE_SECRET_KEY'=>'dummy-secret',
        'TWILIO_ACCOUNT_SID'=>'dummy-account','TWILIO_AUTH_TOKEN'=>'dummy-token','TWILIO_VERIFY_SID'=>'dummy-service',
        'AUTOAGORA_CONNECT_TEST_PHONES'=>['+35790000001'=>hash('sha256','123456')],'AUTOAGORA_CONNECT_TEST_EXPIRES'=>time()+1000] as $k=>$v) { define($k,$v); }
    final class WP_Error {
        public function __construct(public string $code,public string $message,public array $data=[]) {}
    }
    function is_wp_error($v) { return $v instanceof WP_Error; }
    function wp_get_environment_type() { return $GLOBALS['test_environment']; }
    function wp_salt($key) { return 'not-a-real-salt'; }
    function home_url() { return 'https://signup-test.invalid/'; }
    function wp_parse_url($url,$component) { return parse_url($url,$component); }
    function wp_remote_post($url,$args) {
        $GLOBALS['http_calls'][]=[$url,$args];
        if ($GLOBALS['http_throw']) { throw new RuntimeException('Sensitive upstream detail must not escape'); }
        return ['status'=>$GLOBALS['http_status'],'body'=>is_string($GLOBALS['bot'])?$GLOBALS['bot']:json_encode($GLOBALS['bot'])];
    }
    function wp_remote_retrieve_response_code($response) { return $response['status']; }
    function wp_remote_retrieve_body($response) { return $response['body']; }
    final class Database {
        public string $prefix='test_';
        public function prepare($query,...$args) { return $query; }
        public function query($query) { return true; }
        public function get_var($query) { return $GLOBALS['rate_hits']; }
    }
    $wpdb=new Database();
    require dirname(__DIR__,2).'/includes/dealer-integration/Support.php';
    require dirname(__DIR__,2).'/includes/dealer-integration/Signup.php';
    use AutoAgora\Dealer\Signup;
    $checks=[];
    function reset_provider() {
        $GLOBALS['test_environment']='production';$GLOBALS['rate_hits']=1;$GLOBALS['provider_status']='pending';$GLOBALS['provider_throw']=false;
        $GLOBALS['provider_calls']=[];$GLOBALS['http_calls']=[];$GLOBALS['http_throw']=false;$GLOBALS['http_status']=200;
        $GLOBALS['bot']=['success'=>true,'action'=>'connect_signup','hostname'=>'signup-test.invalid'];
    }
    function check_provider($name,$ok) { global $checks;$checks[]=['check'=>$name,'passed'=>(bool)$ok];echo ($ok?'PASS ':'FAIL ').$name."\n";if(!$ok){exit(1);} }
    reset_provider();$r=Signup::send('+35799123456','');check_provider('Production cannot send without bot proof',is_wp_error($r)&&!$provider_calls);
    foreach (['expired'=>['success'=>false],'wrong hostname'=>['success'=>true,'action'=>'connect_signup','hostname'=>'evil.invalid'],
        'wrong action'=>['success'=>true,'action'=>'different','hostname'=>'signup-test.invalid'],'invalid JSON'=>'not-json'] as $name=>$case) {
        reset_provider();$bot=$case;$r=Signup::send('+35799123456','dummy-response');check_provider($name.' fails before SMS',is_wp_error($r)&&!$provider_calls);
    }
    reset_provider();$http_status=503;$r=Signup::send('+35799123456','dummy-response');check_provider('Bot HTTP error cannot authorize SMS',is_wp_error($r)&&!$provider_calls);
    reset_provider();$r=Signup::send('+35799123456','dummy-response');check_provider('Approved bot proof starts SMS verification',is_string($r)&&$provider_calls===[['+35799123456','sms']]);
    check_provider('Bot validation stays server-to-server and hostname-bound',$http_calls[0][0]==='https://challenges.cloudflare.com/turnstile/v0/siteverify'&&$http_calls[0][1]['body']['response']==='dummy-response');
    reset_provider();$provider_status='approved';$ok=Signup::verify('+35799123456','VE'.str_repeat('b',32),'654321');check_provider('Code verification is bound to the sent verification SID',$ok&&$provider_calls[0][0]===['verificationSid'=>'VE'.str_repeat('b',32),'code'=>'654321']);
    reset_provider();check_provider('Malformed code never reaches provider',!Signup::verify('+35799123456','VE'.str_repeat('b',32),'123')&&!$provider_calls);
    reset_provider();check_provider('Test codes cannot bypass production provider',!Signup::verify('+35790000001','test-fake','123456')&&count($provider_calls)===1);
    reset_provider();$provider_throw=true;$r=Signup::send('+35799123456','dummy-response');check_provider('SMS failure is graceful and sanitized',is_wp_error($r)&&!str_contains($r->message,'Sensitive'));
    reset_provider();$http_throw=true;$r=Signup::send('+35799123456','dummy-response');check_provider('Bot transport failure is graceful and sanitized',is_wp_error($r)&&!str_contains($r->message,'Sensitive'));
    reset_provider();$provider_throw=true;check_provider('Code-check exception does not authenticate',!Signup::verify('+35799123456','VE'.str_repeat('b',32),'123456'));
    reset_provider();$rate_hits=6;$r=Signup::send('+35799123456','dummy-response');check_provider('Rate limiting stops upstream calls',is_wp_error($r)&&!$http_calls&&!$provider_calls);
    reset_provider();$test_environment='staging';$r=Signup::send('+35799123456','');check_provider('Staging cannot send to an unapproved real phone',is_wp_error($r)&&!$http_calls&&!$provider_calls);
    reset_provider();$test_environment='staging';$r=Signup::send('+35790000001','');check_provider('Approved staging phone uses only test transport',is_string($r)&&str_starts_with($r,'test-')&&!$http_calls&&!$provider_calls);
    reset_provider();$test_environment='development';$r=Signup::send('+35799123456','dummy-response');check_provider('Unconfigured development environment fails closed',is_wp_error($r)&&!$http_calls&&!$provider_calls);
    echo count($checks)." offline provider checks passed. No external calls.\n";
}
