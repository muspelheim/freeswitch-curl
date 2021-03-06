<?php
/**
 * @package    FS_CURL
 * @subpackage FS_CURL_Directory
 * iax.conf.php
 */

/**
 * @package    FS_CURL
 * @subpackage FS_CURL_Configuration
 * @license
 * @author     Raymond Chandler (intralanman) <intralanman@gmail.com>
 * @version    0.1
 * @todo       add gateways, aliases, etc when support is added into FS
 * Class to write XML for iax.conf
 */
class iax_conf extends fs_configuration
{
    public function main()
    {
        $profiles = $this->get_profiles();
        $this->write_config($profiles);
    }

    private function get_profiles()
    {
        $query = "SELECT * FROM iax_conf ORDER BY id LIMIT 1";
        $res = $this->db->query($query);
        $res = $res->fetchAll();

        return $res;
    }

    private function write_aliases($profile_id)
    {
        $query = "SELECT * FROM iax_aliases WHERE iax_id=$profile_id ";
        $res = $this->db->query($query);
        $aliases_array = $res->fetchAll();
        $aliases_count = count($aliases_array);

        if ($aliases_count < 1) {
            return;
        }
        $this->xmlw->startElement('aliases');

        for ($i = 0; $i < $aliases_count; $i++) {
            //$this -> comment_array($aliases_array[$i]);
            $this->xmlw->startElement('param');
            $this->xmlw->writeAttribute('name', $aliases_array[$i]['alias_name']);
            $this->xmlw->endElement();//</param>
        }
        $this->xmlw->endElement();
    }

    private function write_settings($profile_id)
    {
        $query = "SELECT * FROM iax_settings WHERE iax_id=$profile_id "
            . "ORDER BY iax_id, param_name";
        $res = $this->db->query($query);
        $settings_array = $res->fetchAll();
        $settings_count = count($settings_array);

        if ($settings_count < 1) {
            return;
        }
        $this->xmlw->startElement('settings');

        for ($i = 0; $i < $settings_count; $i++) {
            //$this -> comment_array($settings_array[$i]);
            $this->xmlw->startElement('param');
            $this->xmlw->writeAttribute('name', $settings_array[$i]['param_name']);
            $this->xmlw->writeAttribute('value', $settings_array[$i]['param_value']);
            $this->xmlw->endElement();//</param>
        }
        $this->xmlw->endElement();
    }

    private function write_gateways($profile_id)
    {
        $query = "SELECT * FROM iax_gateways WHERE iax_id=$profile_id "
            . "ORDER BY gateway_name, gateway_param";
        $res = $this->db->query($query);
        $gateway_array = $res->fetchAll();
        $gateway_count = count($gateway_array);

        if ($gateway_count < 1) {
            return;
        }
        $this->xmlw->startElement('gateways');
        for ($i = 0; $i < $gateway_count; $i++) {
            $this_gateway = $gateway_array[$i]['gateway_name'];
            if ($this_gateway != $gateway_array[$i - 1]['gateway_name']) {
                $this->xmlw->startElement('gateway');
                $this->xmlw->writeAttribute('name', $this_gateway);
            }
            $this->xmlw->startElement('param');
            $this->xmlw->writeAttribute('name', $gateway_array[$i]['gateway_param']);
            $this->xmlw->writeAttribute('value', $gateway_array[$i]['gateway_value']);
            $this->xmlw->endElement();
            if (!array_key_exists($i + 1, $gateway_array)
                || $this_gateway != $gateway_array[$i + 1]['gateway_name']
            ) {
                $this->xmlw->endElement();
            }
            $last_gateway = $this_gateway;
        }
        $this->xmlw->endElement(); //</gateways>
    }

    private function write_domains($profile_id)
    {
        $query = "SELECT * FROM iax_domains WHERE iax_id=$profile_id";
        $res = $this->db->query($query);
        $domain_array = $res->fetchAll();
        $domain_count = count($domain_array);

        if ($domain_count < 1) {
            return;
        }
        $this->xmlw->startElement('domains');
        for ($i = 0; $i < $domain_count; $i++) {
            $this->xmlw->startElement('domain');
            $this->xmlw->writeAttribute('name', $domain_array[$i]['domain_name']);
            $this->xmlw->writeAttribute(
                'parse', ($domain_array[$i]['parse'] == 1 ? 'true' : 'false')
            );
            $this->xmlw->endElement();
        }
        $this->xmlw->endElement();
    }

    private function write_single_profile($profile)
    {
        /*  //reserved for multi-profile
        $this -> xmlw -> startElement('profile');
        $this -> xmlw -> writeAttribute('name', $profile['profile_name']);
        */
        //$this -> write_aliases($profile['id']);
        //$this -> write_domains($profile['id']);
        //$this -> write_gateways($profile['id']);
        $this->write_settings($profile['id']);
        //$this -> xmlw -> endElement(); //reserved for multi-profile
    }

    /**
     * Write XML for iax.conf profiles
     *
     * @param array $profiles
     */
    private function write_config($profiles)
    {
        $profile_count = count($profiles);
        $this->xmlw->startElement('configuration');
        $this->xmlw->writeAttribute('name', basename(__FILE__, '.php'));
        $this->xmlw->writeAttribute('description', 'IAX Endpoint');
        /*
        $this -> xmlw -> startElement('profiles');
        //we'll add this back if ever multiple iax profiles are supported
        */
        for ($i = 0; $i < $profile_count; $i++) {
            $this->write_single_profile($profiles[$i]);
        }
        //$this -> xmlw -> endElement(); //this too
        $this->xmlw->endElement();
    }
}
