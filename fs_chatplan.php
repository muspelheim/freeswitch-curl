<?php

/**
 * @package FS_CURL
 * @license BSD
 * @author  Raymond Chandler (intralanman) <intralanman@gmail.com>
 * @version 0.1
 * Class for XML chatplan
 */
class fs_chatplan extends fs_curl
{
	private $special_class_file;

	/**
	 * This is the method that determines the XML output. Customized chatplans can
	 * be easily created by adding a record to the chatplan_special table with the
	 * appropriate values. The php class MUST contain a "main()" method. The method
	 * should write directly to the xmlw obj that's pased or take care of writing
	 * out the xml itself and exiting as to not return.
	 *
	 */
	public function main()
	{
		$this->comment($this->request);
		$context = $this->request['context'];
		if ($this->is_specialized_chatplan($context)) {
			$this->debug("$context should be handled in a specialized chatplan class file");
			if (!include_once($this->special_class_file)) {
				$this->file_not_found();
			}
			$class = sprintf('chatplan_%s', $context);
			if (!class_exists($class)) {
				$this->comment("No Class of name $class");
				$this->file_not_found();
			}
			$obj = new $class;
			/**
			 * recieving method should take incoming parameter as &$something
			 */
			$obj->main($this);
		} else {
			$dp_array = $this->get_chatplan($context);
			$this->writeChatplan($dp_array);
		}
		$this->output_xml();
	}

	public function is_specialized_chatplan($context)
	{
		$query = sprintf(
			"SELECT * FROM chatplan_special WHERE context='%s'", $context
		);
		$this->debug($query);
		$res = $this->db->query($query);

		if ($res->rowCount() == 1) {
			$row = $res->fetch();
			$this->debug($row);
			$this->special_class_file = sprintf('chatplans/%s', $row['class_file']);
			return true;
		} else {
			return false;
		}
	}

	/**
	 * This method will pull chatplan from database
	 *
	 * @param string $context context name for XML chatplan
	 *
	 * @return array
	 */
	private function get_chatplan($context)
	{
		$dp_array = [];
		$dpQuery = sprintf('SELECT
		%1$scontext%1$s,
		%1$sname%1$s AS extension,
		%1$sapplication%1$s AS application_name,
		%1$sdata%1$s AS application_data,
		%1$sfield%1$s AS condition_field,
		%1$sexpression%1$s AS condition_expression,
		%1$scontinue%1$s AS ext_continue,
		%1$stype%1$s
	FROM chatplan
		INNER JOIN chatplan_context USING(chatplan_id)
		INNER JOIN chatplan_extension USING(context_id)
		INNER JOIN chatplan_condition USING(extension_id)
		INNER JOIN chatplan_actions USING(condition_id)
	WHERE CONTEXT = \'%2$s\'
	ORDER BY chatplan_context.weight,
			 chatplan_extension.weight,
			 chatplan_condition.weight,
			 chatplan_actions.weight'
			, DB_FIELD_QUOTE, $context
		);
		$this->debug($dpQuery);
		$res = $this->db->query($dpQuery);

		if ($res->rowCount() < 1) {
			$this->debug("nothing to do, let's just return not found");
			$this->file_not_found();
		}

		while ($row = $res->fetch()) {
			$ct = $row['context'];
			$et = $row['extension'];
			$ec = $row['ext_continue'];
			$app = $row['application_name'];
			$data = $row['application_data'];
			//$app_cdata = $row['app_cdata'];
			$type = $row['type'];
			$cf = $row['condition_field'];
			$ce = $row['condition_expression'];
			//$rcd = $row['re_cdata'];
			$cc = empty($row['cond_break']) ? '0' : $row['cond_break'];
			$dp_array[$ct]["$et;$ec"]["$cf;$ce;$cc"][] = [
				'type'        => $type,
				'application' => $app,
				'data'        => $data,
				'is_cdata'    => (empty($app_cdata) ? '' : $app_cdata),
			];
		}
		return $dp_array;
	}

	/**
	 * Write XML chatplan from the array returned by get_chatplan
	 * @see  fs_chatplan::get_chatplan
	 *
	 * @param array $dpArray Multi-dimentional array from which we write the XML
	 *
	 * @todo this method should REALLY be broken down into several smaller methods
	 *
	 */
	private function writeChatplan($dpArray)
	{
		//print_r($dpArray);
		if (is_array($dpArray)) {
			$this->xmlw->startElement('section');
			$this->xmlw->writeAttribute('name', 'chatplan');
			$this->xmlw->writeAttribute('description', 'FreeSWITCH Chatplan');
			//$this -> comment('dpArray is an array');
			foreach ($dpArray as $context => $extensions_array) {
				//$this -> comment($context);
				//start the context
				$this->xmlw->startElement('context');
				$this->xmlw->writeAttribute('name', $context);
				if (is_array($extensions_array)) {
					foreach ($extensions_array as $extension => $conditions) {
						//start an extension
						$ex_split = preg_split('/;/', $extension);
						$this->xmlw->startElement('extension');
						$this->xmlw->writeAttribute('name', $ex_split[0]);
						if (strlen($ex_split[1]) > 0) {
							$this->xmlw->writeAttribute('continue', $ex_split[1]);
						}
						$this->debug($conditions);
						foreach ($conditions as $condition => $app_array) {
							$c_split = preg_split('/;/', $condition);
							$this->xmlw->startElement('condition');
							if (!empty($c_split[0])) {
								$this->xmlw->writeAttribute('field', $c_split[0]);
							}
							if (!empty($c_split[1])) {
								if (array_key_exists(3, $c_split)
									&& $c_split[3] == true
								) {
									$this->xmlw->startElement('expression');
									$this->xmlw->writeCdata($c_split[1]);
									$this->xmlw->endElement();
								} else {
									$this->xmlw->writeAttribute(
										'expression', $c_split[1]
									);
								}
							}
							//$this -> debug($c_split[2]);
							if ($c_split[2] != '0') {
								$this->xmlw->writeAttribute(
									'break', $c_split[2]
								);
							}
							//$this -> debug($app_array);
							foreach ($app_array as $app) {
								if (empty($app['application'])) {
									continue;
								}
								$this->xmlw->startElement($app['type']);
								$this->xmlw->writeAttribute(
									'application', $app['application']
								);
								if (!empty($app['data'])) {
									if (array_key_exists('is_cdata', $app)
										&& $app['is_cdata'] == true
									) {
										$this->xmlw->writeCdata($app['data']);
									} else {
										$this->xmlw->writeAttribute(
											'data', $app['data']
										);
									}
								}
								if ($app['application'] == 'set') {
									$this->xmlw->writeAttribute('inline', 'true');
								}
								$this->xmlw->endElement();
							}
							//</condition>
							$this->xmlw->endElement();
						}
						// </extension>
						$this->xmlw->endElement();
					}
				}
				// </context>
				$this->xmlw->endElement();
			}
			// </section>
			$this->xmlw->endElement();
		}
	}
}