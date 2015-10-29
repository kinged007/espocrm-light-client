<?php

include("espocrm-light-client.php");
$client = new EspoCRMLightClient\Init(
	'http://{{DOMAIN}}/api/v1/', // replace {{DOMAIN}} with the domain that is hosted EspoCRM
	['Calendar', 'Document']
); 

?>