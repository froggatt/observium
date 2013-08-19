<?php

$oid = $mempool['mempool_index'];

if ($debug) { echo("JunOS Mempool"); }

if (!is_array($mempool_cache['junos']))
{
  if ($debug) { echo("caching"); }
  $mempool_cache['junos'] = array();
  $mempool_cache['junos'] = snmpwalk_cache_multi_oid($device, "jnxOperatingBuffer", $mempool_cache['junos'], "JUNIPER-MIB" , mib_dirs('junos'));
  $mempool_cache['junos'] = snmpwalk_cache_multi_oid($device, "jnxOperatingDRAMSize", $mempool_cache['junos'], "JUNIPER-MIB" , mib_dirs('junos'));
  $mempool_cache['junos'] = snmpwalk_cache_multi_oid($device, "jnxOperatingMemory", $mempool_cache['junos'], "JUNIPER-MIB" , mib_dirs('junos'));
  if ($debug) { print_vars($mempool_cache); }
}

$entry = $mempool_cache['junos'][$mempool[mempool_index]];

$perc = $entry['jnxOperatingBuffer'];
// FIX ME -- Maybe another OID? Some equipment do not provide jnxOperatingDRAMSize like MX960
if ($entry['jnxOperatingDRAMSize']) $memory_available = $entry['jnxOperatingDRAMSize'];
else $memory_available = ($entry['jnxOperatingMemory'] * 1024 * 1024);
$mempool['total'] = $memory_available;
$mempool['used'] = $memory_available / 100 * $perc;
$mempool['free'] = $memory_available - $mempool['used'];

?>
