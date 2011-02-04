<?php
/**
 * Varnish admin page
 * included in wpv_admin_page function
 * @todo make nice tabbed interface
 */

if ( ! current_user_can('manage_options') ){
    wp_die( __('You do not have sufficient permissions to access this page.') );
}

// Dodge Wordpress's rediculous magic quotes nonsense
$postdata = wpv_postdata( file_get_contents('php://input') );

// save posted settings
if( isset($postdata['wpv_save']) ){
    update_option( 'wpv_clients', $postdata['wpv_clients'] );
    update_option( 'wpv_secrets', $postdata['wpv_secrets'] );
    update_option( 'wpv_host_pattern', $postdata['wpv_host_pattern'] );
    update_option( 'wpv_path', $postdata['wpv_path'] );
    update_option( 'wpv_enabled', ! empty($postdata['wpv_enabled']) );
}

// perform manual purge
else if( isset($postdata['wpv_purge']) ){
    $purge = $postdata['wpv_purge_pattern'];
    $purgesecret = $postdata['wpv_secret'];
    $timeout = $postdata['wpv_timeout'] or $timeout = 3;
    foreach( wpv_get_clients() as $client ){
        list( $host, $port ) = $client;
        try {
            $Sock = wpv_admin_socket( $host, $port );
            $Sock->set_auth( rawurldecode($purgesecret) );
            @$Sock->connect( $timeout );
            $Sock->purge( $purge );
            $purges[$host.':'.$port] = 'Purged';
        }
        catch( Exception $Ex ){
            $purges[$host.':'.$port] = 'Error: '.$Ex->getMessage();
        }
    }
}

// perform ping / diagnostics
else if( isset($postdata['wpv_ping']) ){
    $pingclients = $postdata['wpv_clients'];
    $pingsecret = $postdata['wpv_secret'];
    $timeout = $postdata['wpv_timeout'] or $timeout = 3;
    foreach( wpv_get_clients($pingclients) as $client ){
        list( $host, $port ) = $client;
        try {
            $Sock = wpv_admin_socket( $host, $port );
            $Sock->set_auth( rawurldecode($pingsecret) );
            @$Sock->connect( $timeout );
            $ping[$host.':'.$port] = $Sock->status() ? 'Running :)' : 'Stopped, but responding';
        }
        catch( Exception $Ex ){
            $ping[$host.':'.$port] = 'Error: '.$Ex->getMessage();
        }
    }
}

// get current raw clients string setting
$enabled = get_option('wpv_enabled');
$clients = get_option('wpv_clients', '127.0.0.1:6082');
$secrets = get_option('wpv_secrets', '');

// get current hostpattern setting
$hostpattern = get_option('wpv_host_pattern',''); 
if( ! $hostpattern && preg_match('![^\./]+\.[a-z]+!i',get_option('siteurl'),$r) ){
	$hostpattern = str_replace('.','\\\\.',$r[0]).'$';
}

$path = get_option('wpv_path',''); 

// defaults that may not have been set
isset($pingclients) or $pingclients = $clients;
isset($pingsecret) or $pingsecret = current( preg_split('/(\r|\n|\r)/',$secrets) );
isset($timeout) or $timeout = 3;
isset($purge) or $purge = 'req.url ~ "^/$"'.($hostpattern ? ' && req.http.host ~ "'.$hostpattern.'"' : '');

?>


    <div class="wrap">
    	<h2>Varnish admin</h2>
    	<h3>Configure Varnish clients</h3>
    	<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>" enctype="application/x-www-form-urlencoded">
    		<fieldset>
    			<label>
    				<input type="checkbox" name="wpv_enabled" value="1"<?php echo $enabled?' checked="checked"':''?> /> 
    				Enable purging when content changes
    			</label>
    		</fieldset>
    		<br />
    		<fieldset>
    			<label for="f_wpv_path">Root path (eg <code>/blog</code>)</label>: <input type="text" name="wpv_path" id="f_wpv_path" value="<?php echo esc_html($path)?>" size="32" />
    		</fieldset>
    		<fieldset>
    			<label for="f_wpv_host_pattern">Specify a host name pattern for purge commands</label> <br />
    			req.http.host ~ <input type="text" name="wpv_host_pattern" id="f_wpv_host_pattern" value="<?php echo esc_html($hostpattern)?>" size="32" />
    		</fieldset>
            <br />
            <fieldset style=" display:block; float:left; width:15%">
                <label for="f_wpv_clients">Specify Varnish clients (<em>host:port</em>) </label> <br />
                <textarea name="wpv_clients" id="f_wpv_clients" rows="3" cols="30" wrap="off"><?php echo esc_html($clients)?></textarea>
            </fieldset>
            <fieldset style=" display:block; float:left; width:60%">
                <label for="f_wpv_clients">Specify authentication secrets (<em>url encoded</em>)</label> <br />
                <textarea name="wpv_secrets" id="f_wpv_secrets" rows="3" cols="60" wrap="off"><?php echo esc_html($secrets)?></textarea>
            </fieldset>
    		<fieldset style="display:block; clear:both">
    		    <input type="submit" value="Save config" name="wpv_save" />
    		</fieldset>
    	</form>

    	
    	<a name="purge"></a>
    	<?php if( ! empty($purges) ): ?>
    	<h3>Purge results</h3>
    	<?php foreach( $purges as $client => $result ):?>
		<div>
			<strong><?php echo esc_html($client)?></strong>
			<pre><?php echo esc_html($result)?></pre>
		</div>
    	<?php endforeach?>
    	<?php endif?>
    
       	<h3>Manual purge</h3>
    	<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>#purge" enctype="application/x-www-form-urlencoded">
    		<fieldset>
        		<input type="text" name="wpv_purge_pattern" id="f_wpv_purge" value="<?php echo esc_html($purge)?>" size="100" />
    		</fieldset>
    		<fieldset>
    			timeout: <input type="text" name="wpv_timeout" id="f_wpv_timeout" value="<?php echo esc_html($timeout)?>" size="4" /> secs
    		</fieldset>
    		<input type="submit" value="Purge" name="wpv_purge" />
    	</form>
    	
    	
        <a name="ping"></a>
        <?php if( ! empty($ping) ): ?>
        <h3>Ping results</h3>
        <?php foreach( $ping as $client => $result ):?>
        <div>
            <strong><?php echo esc_html($client)?></strong>
            <pre><?php echo esc_html($result)?></pre>
        </div>
        <?php endforeach?>
        <?php endif?>

        <h3>Ping Varnish clients</h3>
        <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>#ping" enctype="application/x-www-form-urlencoded">
            <fieldset>
                client: <input type="text" name="wpv_clients" id="f_wpv_clients" value="<?php echo esc_html($pingclients)?>" size="100" />
            </fieldset>
            <fieldset>
               secret: <input type="text" name="wpv_secret" id="f_wpv_secret" value="<?php echo esc_html($pingsecret)?>" size="100" />
            </fieldset>
            <fieldset>
                timeout: <input type="text" name="wpv_timeout" id="f_wpv_timeout" value="<?php echo esc_html($timeout)?>" size="4" /> secs
            </fieldset>
            
            <input type="submit" value="Ping" name="wpv_ping" />
        </form>    	
    	
    	
    	<h3>Help</h3>
    	<p>Tweet <a href="http://twitter.com/timwhitlock" target="_blank">@timwhitlock</a> and I'll try to help</p>
    	
    </div>
    