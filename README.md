NETCONF
-------
This is a vendor-agnostic PHP implementation of NETCONF.  I developed this so I could extend a Junos (Juniper) specific
NETCONF API off of it, and decided to share it publicly.

Targeted RFCs
-------------
 - RFC6241 - Network Configuration Protocol (NETCONF) - https://tools.ietf.org/html/rfc6241
 - RFC6242 - Using the NETCONF Protocol over Secure Shell (SSH) - https://tools.ietf.org/html/rfc6242

Dependencies
-------------
 - PHP >= 5.4
 - phpseclib (https://github.com/phpseclib/phpseclib)


To Do
----------
- Attributes for sendRaw need to be sanitized correctly (https://tools.ietf.org/html/rfc6241)
- Elements with attribute naming for subtree filters need to be implemented ("6.4.8.  Elements with Attribute Naming")
- Parse capabilities based on IANA list: http://www.iana.org/assignments/netconf-capability-urns/netconf-capability-urns.xhtml


Examples
------------

Initializing NETCONF using password authentication and then sending a custom RPC call
---------------------------
```php
$netConf = new NetConf(
    "192.168.0.100",
    new NetConfAuthPassword(
        [
            "username" => "lamoni",
            "password" => "phpsux"
        ]
    )
);

echo $netConf->sendRPC(
    "<get-config>".
        "<source>".
            "<running/>".
        "</source>".
    "</get-config>"
);
```
---------------------------

Editing the configuration of a Junos device and committing the changes
---------------------------

```php
$netConf->editConfig(
    "<configuration>
        <interfaces>
            <interface>
                <name>fe-0/0/0</name>
                <description>Testing netconf</description>
            </interface>
        </interfaces>
    </configuration>",
    'candidate',
    ['custom-param' => 'custom-value']
);


if ($netConf->commit()->isRPCReplyOK()) {
    echo "Successfully committed, dude!";
}
else {
    echo "Something's wrong, man.";
}
```
---------------------------

Using NETCONF's subtree filters to get certain config
---------------------------
```php
$getUsersNames = $netConf->getConfig(
    [
       "configuration/system/login/user" => [
           [
               "name"=>"user"
           ]
       ]
    ]
);
```
---------------------------

Considerations
--------------
- Namespaces?
- Heavier use of Exceptions?
- test-option:  The <test-option> element MAY be specified only if the device advertises the :validate:1.1 capability (Section 8.6).
- Should I be implicitly locking/unlocking the config for editConfig() (<edit-config>) and commit() (<commit>) calls?
- Should I remove the long list of arguments for argument-heavy methods and replace them with a single array?
    - Pros
        - it looks nicer
        - allows for extension in the future without requiring heavy refactoring
    - Cons
        - Methods will require some extract() type code at the top, along with validation of keys passed through (abstract class that?)
        - IDEs argument suggestion won't work(?)
- XPath capability in filter?