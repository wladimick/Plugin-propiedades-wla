<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WLA\Inmo\Import\DnsResolverInterface;
use WLA\Inmo\Import\NetworkAddressPolicy;
use WLA\Inmo\Import\RemoteMediaException;
use WLA\Inmo\Import\RemoteMediaUrlPolicy;

final class ImportRemoteMediaUrlPolicyTest extends TestCase
{
	public function testPublicHttpsUrlIsAccepted(): void
	{
		$policy = $this->policy(array('images.example.com' => array('93.184.216.34')));
		self::assertSame('https://images.example.com/casa.jpg', $policy->validate('https://images.example.com/casa.jpg'));
	}

	public function testOnlyHttpAndHttpsAreAllowed(): void
	{
		$this->expectReason('invalid_media_scheme', fn () => $this->policy()->validate('ftp://example.com/image.jpg'));
	}

	public function testCredentialsFragmentsAndNonStandardPortsAreRejected(): void
	{
		$this->expectReason('media_credentials_forbidden', fn () => $this->policy()->validate('https://user:pass@example.com/image.jpg'));
		$this->expectReason('media_fragment_forbidden', fn () => $this->policy()->validate('https://example.com/image.jpg#fragment'));
		$this->expectReason('blocked_media_port', fn () => $this->policy()->validate('https://example.com:8080/image.jpg'));
	}

	public function testLocalAndSingleLabelHostsAreRejectedBeforeDns(): void
	{
		$this->expectReason('blocked_media_host', fn () => $this->policy()->validate('http://localhost/image.jpg'));
		$this->expectReason('blocked_media_host', fn () => $this->policy()->validate('http://wordpress/image.jpg'));
		$this->expectReason('blocked_media_host', fn () => $this->policy()->validate('http://printer.local/image.jpg'));
	}

	public function testPrivateLoopbackLinkLocalAndDocumentationRangesAreBlocked(): void
	{
		foreach (array('127.0.0.1', '10.0.0.1', '172.16.5.10', '192.168.1.1', '169.254.169.254', '100.64.0.1', '198.18.0.1', '203.0.113.5') as $address) {
			self::assertFalse(NetworkAddressPolicy::isPublic($address), $address . ' must be blocked.');
		}
	}

	public function testPrivateAndMixedDnsAnswersAreRejected(): void
	{
		$this->expectReason(
			'blocked_media_address',
			fn () => $this->policy(array('private.example.com' => array('10.0.0.8')))->validate('https://private.example.com/a.jpg')
		);
		$this->expectReason(
			'blocked_media_address',
			fn () => $this->policy(array('mixed.example.com' => array('93.184.216.34', '169.254.169.254')))->validate('https://mixed.example.com/a.jpg')
		);
	}

	public function testIpv6LoopbackUniqueLocalLinkLocalAndMappedIpv4AreBlocked(): void
	{
		foreach (array('::1', 'fc00::1', 'fd00::1', 'fe80::1', '::ffff:127.0.0.1') as $address) {
			self::assertFalse(NetworkAddressPolicy::isPublic($address), $address . ' must be blocked.');
		}
		self::assertTrue(NetworkAddressPolicy::isPublic('2606:4700:4700::1111'));
	}

	public function testLiteralPrivateIpIsRejectedWithoutDnsLookup(): void
	{
		$this->expectReason('blocked_media_address', fn () => $this->policy()->validate('http://169.254.169.254/latest/meta-data'));
	}

	public function testUnresolvedHostIsRejected(): void
	{
		$this->expectReason('media_dns_failed', fn () => $this->policy()->validate('https://missing.example.com/image.jpg'));
	}

	public function testUrlListIsBoundedAndDeduplicated(): void
	{
		$policy = $this->policy(array('images.example.com' => array('93.184.216.34')), 2);
		self::assertSame(
			array('https://images.example.com/a.jpg'),
			$policy->validateList(array('https://images.example.com/a.jpg', 'https://images.example.com/a.jpg'))
		);
		$this->expectReason(
			'media_url_limit_exceeded',
			fn () => $policy->validateList(array(
				'https://images.example.com/a.jpg',
				'https://images.example.com/b.jpg',
				'https://images.example.com/c.jpg',
			))
		);
	}

	/** @param array<string,array<int,string>> $records */
	private function policy(array $records = array(), int $maxUrls = 20): RemoteMediaUrlPolicy
	{
		$resolver = new class($records) implements DnsResolverInterface {
			/** @param array<string,array<int,string>> $records */
			public function __construct(private array $records)
			{
			}

			/** @return array<int,string> */
			public function resolve(string $host): array
			{
				return $this->records[$host] ?? array();
			}
		};

		return new RemoteMediaUrlPolicy($resolver, $maxUrls);
	}

	private function expectReason(string $reason, callable $callback): void
	{
		try {
			$callback();
			self::fail('Expected RemoteMediaException: ' . $reason);
		} catch (RemoteMediaException $exception) {
			self::assertSame($reason, $exception->reason());
		}
	}
}
