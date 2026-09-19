<?php
/**
 * Liste les derniers runs GitHub Actions du dépôt.
 * Usage : wp eval-file tools/dev/check-runs.php
 */

$cred  = shell_exec( 'printf "protocol=https\nhost=github.com\n\n" | git credential fill' );
$token = '';
foreach ( explode( "\n", (string) $cred ) as $line ) {
	if ( 0 === strpos( trim( $line ), 'password=' ) ) {
		$token = trim( substr( trim( $line ), 9 ) );
	}
}
$ch = curl_init( 'https://api.github.com/repos/derouicheoussama/infinitycod/actions/runs?per_page=6' );
curl_setopt_array( $ch, array(
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_HTTPHEADER     => array( 'Authorization: token ' . $token, 'User-Agent: icod' ),
) );
$r = json_decode( curl_exec( $ch ), true );
foreach ( ( $r['workflow_runs'] ?? array() ) as $run ) {
	echo str_pad( $run['name'], 22 ), ' | ', str_pad( $run['head_branch'], 10 ), ' | ', str_pad( $run['status'], 12 ), ' | ', $run['conclusion'], ' | ', $run['created_at'], "\n";
}
