<?php

echo 'JUNIPER-VLAN-MIB VLANs: ';

$vlanversion = snmp_get($device, 'dot1qVlanVersionNumber.0', '-Oqv', 'IEEE8021-Q-BRIDGE-MIB');

if ($vlanversion == 'version1' || $vlanversion == '2') {
    echo "ver $vlanversion ";
    $vtpdomain_id = '1';
    $vlans        = snmpwalk_cache_oid($device, 'jnxExVlanName', array(), 'JUNIPER-VLAN-MIB');
    if (empty($vlans)) {
        $vlans      = snmpwalk_cache_oid($device, 'jnxL2aldVlanName', array(), 'JUNIPER-L2ALD-MIB');
        $tagoruntag = snmpwalk_cache_oid($device, 'jnxL2aldVlanTag', array(), 'JUNIPER-L2ALD-MIB', null, ['-OQUs', '--hexOutputLength=0']);
        $untag      = snmpwalk_cache_oid($device, 'jnxExVlanPortTagness', array(), 'JUNIPER-VLAN-MIB', null, ['-OQeUs', '--hexOutputLength=0']);
        $tmp_tag    = 'jnxL2aldVlanTag';
        $tmp_name   = 'jnxL2aldVlanName';
    } else {
        $tagoruntag = snmpwalk_cache_oid($device, 'jnxExVlanTag', array(), 'JUNIPER-VLAN-MIB', null, ['-OQUs', '--hexOutputLength=0']);
        $untag      = snmpwalk_cache_oid($device, 'jnxExVlanPortTagness', array(), 'JUNIPER-VLAN-MIB', null, ['-OQeUs', '--hexOutputLength=0']);
        $tmp_tag    = 'jnxExVlanTag';
        $tmp_name   = 'jnxExVlanName';
    }

    foreach ($untag as $key => $tagness) {
    	$key = explode('.', $key);
        if ($tagness['jnxExVlanPortTagness'] == 2) {
            $tagness_by_vlan_index[$key[0]][$base_to_index[$key[1]]]['tag'] = 1;
        } else {
    	    $tagness_by_vlan_index[$key[0]][$base_to_index[$key[1]]]['tag'] = 0;
        }
    }

    foreach ($vlans as $vlan_index => $vlan) {
        $vlan_id = $tagoruntag[$vlan_index][$tmp_tag];
        d_echo(" $vlan_id");
        if (is_array($vlans_db[$vtpdomain_id][$vlan_id])) {
            $vlan_data = $vlans_db[$vtpdomain_id][$vlan_id];
            if ($vlan_data['vlan_name'] != $vlan[$tmp_name]) {
                $vlan_upd['vlan_name'] = $vlan[$tmp_name];
                dbUpdate($vlan_upd, 'vlans', '`vlan_id` = ?', array($vlan_data['vlan_id']));
                log_event("VLAN $vlan_id changed name {$vlan_data['vlan_name']} -> {$vlan[$tmp_name]} ", $device, 'vlan', 3, $vlan_data['vlan_id']);
                echo 'U';
            } else {
                echo '.';
            }
        } else {
            dbInsert(array(
                'device_id' => $device['device_id'],
                'vlan_domain' => $vtpdomain_id,
                'vlan_vlan' => $vlan_id,
                'vlan_name' => $vlan[$tmp_name],
                'vlan_type' => array('NULL')
            ), 'vlans');
            echo '+';
        }
        $device['vlans'][$vtpdomain_id][$vlan_id] = $vlan_id;
        foreach ($tagness_by_vlan_index[$vlan_index] as $ifIndex => $tag) {
	        $per_vlan_data[$vlan_id][$ifIndex]['untagged'] = $tag['tag'];
	    }

    }
}
