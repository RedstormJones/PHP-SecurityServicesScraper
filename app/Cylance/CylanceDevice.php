<?php

namespace App\Cylance;

use Illuminate\Database\Eloquent\Model;

class CylanceDevice extends Model
{
    /*
    * The attributes that are mass assignable.
    *
    * @var array
    */
    protected $fillable = [
        'Abnormal',
        'AgentVersionText',
        'BackgroundDetection',
        'ClientStatus',
        'Created',
        'DeviceId',
        'DeviceLdapDistinguishedName',
        'DeviceLdapGroupMembership',
        'DnsName',
        'FilesAnalyzed',
        'IPAddresses',
        'IPAddressesText',
        'IsOffline',
        'IsSafe',
        'LastUsersText',
        'MacAddressesText',
        'MemoryProtection',
        'Name',
        'OfflineDate',
        'OSVersionsText',
        'PolicyName',
        'Quarantined',
        'RequiresUpdate',
        'ScriptCount',
        'Unsafe',
        'Waived',
        'ZoneRole',
        'ZoneRoleText',
        'Zones',
        'ZonesText',
    ];
}
