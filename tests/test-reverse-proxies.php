<?php

function option_use_proxy() {
	return 1;
}

function test_set_trusted_proxies() {
	return '1.1.1.1, 2.2.2.2, 3.3.3.3, FE80::0202:B3FF:FE1E:8329';
}

require_once(GEOIP_PLUGIN_DIR . '/lib/dynamic-reverse-proxies/abstract.php');

class ReverseProxyTest extends WP_UnitTestCase_GeoIP_Detect {
	
	
	function set_up() {
		parent::set_up();
		
		$_SERVER['HTTP_X_FORWARDED_FOR'] = GEOIP_DETECT_TEST_IP;
		
		add_filter('pre_option_geoip-detect-has_reverse_proxy', 'option_use_proxy', 101);
	}
	function tear_down() {
		
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '';
		
		remove_filter('pre_option_geoip-detect-has_reverse_proxy', 'option_use_proxy', 101);
		remove_filter('pre_option_geoip-detect-trusted_proxy_ips', 'test_set_trusted_proxies', 101);
		
		parent::tear_down();
	}
	
	function testGetClientIpWithoutOption() {
		$this->assertSame(false, false);
		remove_filter('pre_option_geoip-detect-has_reverse_proxy', 'option_use_proxy', 101);
		$ip = geoip_detect2_get_client_ip();
		if ($ip !== '::1' && $ip !== '127.0.0.1')
			$this->fail('CLI does not return localhost IP, actual IP: "' . $ip . '"');
	}
	
	function testOneProxy() {
		$this->assertSame(GEOIP_DETECT_TEST_IP, geoip_detect2_get_client_ip());
	}
	
	function testShortcode() {
		$ip = do_shortcode('[geoip_detect2_get_client_ip]');
		$this->assertEquals(GEOIP_DETECT_TEST_IP, $ip);
	}
	
	function testShortcodeIpv6() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = 'fe80:0:0:0:202:b3ff:fe1e:8329';
		
		$ip = do_shortcode('[geoip_detect2_get_client_ip]');
		$this->assertEquals('fe80::202:b3ff:fe1e:8329', $ip);
	}
	
	function testTwoProxies() {
		// Without trusted proxies set
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.1.1.1, ' . GEOIP_DETECT_TEST_IP;
		$this->assertSame(GEOIP_DETECT_TEST_IP, geoip_detect2_get_client_ip());
		
		$_SERVER['HTTP_X_FORWARDED_FOR'] = GEOIP_DETECT_TEST_IP . ', 1.1.1.1';
		$this->assertNotSame(GEOIP_DETECT_TEST_IP, geoip_detect2_get_client_ip());
	}

	function testProxiesWithPort() {
		add_filter('pre_option_geoip-detect-trusted_proxy_ips', 'test_set_trusted_proxies', 101);

		$_SERVER['HTTP_X_FORWARDED_FOR'] = GEOIP_DETECT_TEST_IP . ', 1.1.1.1, 2.2.2.2:45';
		$this->assertSame(GEOIP_DETECT_TEST_IP, geoip_detect2_get_client_ip());

		$_SERVER['HTTP_X_FORWARDED_FOR'] = GEOIP_DETECT_TEST_IP . ', 1.1.1.1, [FE80::0202:B3FF:FE1E:8329]:45';
		$this->assertSame(GEOIP_DETECT_TEST_IP, geoip_detect2_get_client_ip());
	}
	
	function testNormalizeIpv6() {
		$this->assertSame('fe80::202:b3ff:fe1e:8329', geoip_detect_normalize_ip('FE80::0202:B3FF:FE1E:8329'));
		$this->assertSame('fe80::202:b3ff:fe1e:8329', geoip_detect_normalize_ip('fe80:0:0:0:202:b3ff:fe1e:8329'));
	}
	
	function testNormalizeIpv4() {
		$this->assertSame('1.1.1.1', geoip_detect_normalize_ip(' 1.1.1.1 '));
	}
	
	
	function testTrustedProxies() {
		add_filter('pre_option_geoip-detect-trusted_proxy_ips', 'test_set_trusted_proxies', 101);
		
		$_SERVER['HTTP_X_FORWARDED_FOR'] = GEOIP_DETECT_TEST_IP.', 1.1.1.1';
		$this->assertSame(GEOIP_DETECT_TEST_IP, geoip_detect2_get_client_ip());
		
		$_SERVER['HTTP_X_FORWARDED_FOR'] = GEOIP_DETECT_TEST_IP.', 1.1.1.1, 2.2.2.2';
		$this->assertSame(GEOIP_DETECT_TEST_IP, geoip_detect2_get_client_ip());
		
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '2.2.2.3, '. GEOIP_DETECT_TEST_IP.', 1.1.1.1';
		$this->assertSame(GEOIP_DETECT_TEST_IP, geoip_detect2_get_client_ip());

		$_SERVER['HTTP_X_FORWARDED_FOR'] = '2.2.2.2, '. GEOIP_DETECT_TEST_IP.', 1.1.1.1';
		$this->assertSame(GEOIP_DETECT_TEST_IP, geoip_detect2_get_client_ip());

		$_SERVER['HTTP_X_FORWARDED_FOR'] = '3.4.5.6, '. GEOIP_DETECT_TEST_IP.', 2.2.2.2, fe80:0:0:0:202:b3ff:fe1e:8329';
		$this->assertSame(GEOIP_DETECT_TEST_IP, geoip_detect2_get_client_ip());
	}

	function testPrivateIps() {
		add_filter('pre_option_geoip-detect-trusted_proxy_ips', 'test_set_trusted_proxies', 101);

		$_SERVER['HTTP_X_FORWARDED_FOR'] = GEOIP_DETECT_TEST_IP.', 1.1.1.1, 127.0.0.1';
		$this->assertSame(GEOIP_DETECT_TEST_IP, geoip_detect2_get_client_ip());

		$_SERVER['HTTP_X_FORWARDED_FOR'] = GEOIP_DETECT_TEST_IP.', 1.1.1.1, 127.0.0.9';
		$this->assertSame(GEOIP_DETECT_TEST_IP, geoip_detect2_get_client_ip());

		$_SERVER['HTTP_X_FORWARDED_FOR'] = GEOIP_DETECT_TEST_IP.', 1.1.1.1, 10.2.2.2';
		$this->assertSame(GEOIP_DETECT_TEST_IP, geoip_detect2_get_client_ip());

		$_SERVER['HTTP_X_FORWARDED_FOR'] = GEOIP_DETECT_TEST_IP.', 1.1.1.1, 172.16.0.3';
		$this->assertSame(GEOIP_DETECT_TEST_IP, geoip_detect2_get_client_ip());

	}

	/**
	 * @group external-http
	 */
	function testDynamicAws() {
		$object = new DynamicReverseProxiesAws;
		$ips = $object->getIps();

		$this->assertGreaterThan(40, count($ips));
		$this->assertContains('120.52.22.96/27', $ips);
	}
	
	/**
	 * @group external-http
	 */
	function testDynamicCloudflare() {
		$object = new DynamicReverseProxiesCloudflare;
		$ips = $object->getIps();

		$this->assertGreaterThan(15, count($ips));
		$this->assertContains('190.93.240.0/20', $ips);
		$this->assertContains('2405:b500::/32', $ips);
	}
}