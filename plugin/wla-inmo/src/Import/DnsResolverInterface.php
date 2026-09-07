<?php

namespace WLA\Inmo\Import;

interface DnsResolverInterface
{
	/** @return array<int,string> */
	public function resolve(string $host): array;
}
