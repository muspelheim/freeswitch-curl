<?php

/**
 * @package FS_CURL
 * @license BSD
 * @author  Raymond Chandler (intralanman) <intralanman@gmail.com>
 * @version 0.1
 * Class for inserting xml CDR records
 * @return object
 */
class fs_cdr extends fs_curl
{
    /**
     * This variable will hold the XML CDR string
     * @var string
     */
    public $cdr;

    /**
     * This object is the objectified representation of the XML CDR
     * @var SimpleXMLElement
     */
    public $xml_cdr;

    /**
     * This array will hold the db field and their corresponding value
     * @var array
     */
    public $values = [];

    /**
     * This array maps the database field names to XMLSimple paths
     * @var array
     */
    public $fields = [
        'caller_id_name'     => '$this->xml_cdr->callflow[0]->caller_profile->caller_id_name',
        'caller_id_number'   => '$this->xml_cdr->callflow[0]->caller_profile->caller_id_number',
        'destination_number' => '$this->xml_cdr->callflow[0]->caller_profile->destination_number',
        'context'            => '$this->xml_cdr->callflow[0]->caller_profile->context',
        'start_stamp'        => 'urldecode($this->xml_cdr->variables->start_stamp)',
        'answer_stamp'       => 'urldecode($this->xml_cdr->variables->answer_stamp)',
        'end_stamp'          => 'urldecode($this->xml_cdr->variables->end_stamp)',
        'duration'           => '$this->xml_cdr->variables->duration',
        'billsec'            => '$this->xml_cdr->variables->billsec',
        'hangup_cause'       => '$this->xml_cdr->variables->hangup_cause',
        'uuid'               => '$this->xml_cdr->callflow[0]->caller_profile->uuid',
        'bleg_uuid'          => '$this->xml_cdr->callflow[0]->caller_profile->bleg_uuid',
        'accountcode'        => '$this->xml_cdr->variables->accountcode',
        'read_codec'         => '$this->xml_cdr->variables->read_codec',
        'write_codec'        => '$this->xml_cdr->variables->write_codec',
    ];

    /**
     * This is where we instantiate our parent and set up our CDR object
     */
    public function __construct()
    {
        parent::__construct();
        $this->cdr = stripslashes($this->request['cdr']);
        $this->xml_cdr = new SimpleXMLElement($this->cdr);
    }

    /**
     * This is where we run the bulk of our logic through other methods
     */
    public function main()
    {
        $this->set_record_values();
        $this->insert_cdr();
    }

    /**
     * This method will take the db fields and paths defined above and
     * set the values array to be used for the insert
     */
    public function set_record_values()
    {
        /**
         * @var stdClass $xml
         */
        $xml = $this->xml_cdr;
        $callflow = is_array($xml->callflow) ? $xml->callflow[0] : $xml->callflow;
        $caller_profile = $callflow->caller_profile;
        $variables = $xml->variables;

        $this->values = [
            'username'           => $caller_profile->username,
            'caller_id_name'     => $variables->effective_caller_id_name,
            'caller_id_number'   => $variables->effective_caller_id_number,
            'destination_number' => $caller_profile->destination_number,
            'context'            => $caller_profile->context,
            'start_stamp'        => urldecode($variables->start_stamp),
            'answer_stamp'       => urldecode($variables->answer_stamp),
            'end_stamp'          => urldecode($variables->end_stamp),
            'duration'           => $variables->duration,
            'billsec'            => $variables->billsec,
            'hangup_cause'       => $variables->hangup_cause,
            'uuid'               => $caller_profile->uuid,
            'accountcode'        => $variables->accountcode,
            'read_codec'         => $variables->read_codec,
            'write_codec'        => $variables->write_codec,
        ];
        $this->debug($this->values);
    }

    /**
     * finally do the insert of the CDR
     */
    public function insert_cdr()
    {
        $query = sprintf(
            "INSERT INTO cdr (%s) VALUES (%s)",
            join(',', array_keys($this->values)), join(',', $this->values)
        );
        $this->debug($query);
        $this->db->exec($query);
    }
}
