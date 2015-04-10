<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Core;

use OpenImporter\Core\DummyLang;

/**
 * this is our UI
 *
 */
class Template
{
	protected $response = null;
	protected $lng = null;

	public function __construct($lng)
	{
		if ($lng === null)
			$lng = new DummyLang();

		$this->lng = $lng;
	}

	/**
	 * Display a specific error message.
	 *
	 * @param string $error_message
	 * @param int|bool $trace
	 * @param int|bool $line
	 * @param string|bool $file
	 */
	public function error($error_message, $trace = false, $line = false, $file = false)
	{
		echo '
			<div class="error_message">
				<div class="error_text">
					', !empty($trace) ? $this->lng->get(array('error_message', $error_message)) : $error_message, '
				</div>';

		if (!empty($trace))
			echo '
				<div class="error_text">', $this->lng->get(array('error_trace', $trace)), '</div>';

		if (!empty($line))
			echo '
				<div class="error_text">', $this->lng->get(array('error_line', $line)), '</div>';

		if (!empty($file))
			echo '
				<div class="error_text">', $this->lng->get(array('error_file', $file)), '</div>';

		echo '
			</div>';
	}

	public function setResponse($response)
	{
		$this->response = $response;
	}

	public function render()
	{
		// No text? ... so sad. :(
		if ($this->response->no_template)
			return;

		$this->response->sendHeaders();

		// XML ajax feedback? We can just skip everything else
		if ($this->response->is_xml)
			$this->xml();
		elseif ($this->response->is_page)
		{
			$this->header(!$this->response->template_error);

			if ($this->response->template_error)
			{
				foreach ($this->response->getErrors() as $msg)
					$this->error($msg);
			}

			call_user_func_array(array($this, $this->response->use_template), $this->response->params_template);

			$this->footer(!$this->response->template_error);
		}
		else
			call_user_func_array(array($this, $this->response->use_template), $this->response->params_template);
	}

	/**
	 * Show the footer.
	 *
	 * @param bool $inner
	 */
	public function footer($inner = true)
	{
		if (($this->response->step == 1 || $this->response->step == 2) && (bool) $inner === true)
			echo '
				</p>
			</div>';
		echo '
		</div>
	</body>
</html>';
	}

	/**
	 * Show the header.
	 *
	 * @param bool $inner
	 */
	public function header($inner = true)
	{
		echo '<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="', $this->lng->get('locale'), '" lang="', $this->lng->get('locale'), '">
	<head>
		<meta charset="UTF-8" />
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<title>', $this->response->page_title, '</title>
		<script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
		<script type="text/javascript">
			function AJAXCall(url, callback, string)
			{
				var req = init();
				var string = string;
				req.onreadystatechange = processRequest;

				function init()
				{
					if (window.XMLHttpRequest)
						return new XMLHttpRequest();
					else if (window.ActiveXObject)
						return new ActiveXObject("Microsoft.XMLHTTP");
				}

				function processRequest()
				{
					// readyState of 4 signifies request is complete
					if (req.readyState == 4)
					{
						// status of 200 signifies sucessful HTTP call
						if (req.status == 200)
							if (callback) callback(req.responseXML, string);
					}
				}

				// make a HTTP GET request to the URL asynchronously
				this.doGet = function () {
					req.open("GET", url, true);
					req.send(null);
				};
			}
			function validateField(string)
			{
				var target = document.getElementById(string);
				var url = "import.php?action=validate&xml=true&" + string + "=" + target.value.replace(/\/+$/g, "") + "&source=', $this->response->source, '&destination=', $this->response->destination, '";
				var ajax = new AJAXCall(url, validateCallback, string);
				ajax.doGet();
			}

			function validateCallback(responseXML, string)
			{
				var msg = responseXML.getElementsByTagName("valid")[0].firstChild.nodeValue;
				if (msg == "false")
				{
					var field = document.getElementById(string);
					var validate = document.getElementById(\'validate_\' + string);
					field.className = "invalid_field";
					validate.innerHTML = "', $this->lng->get('invalid') , '";
					// set the style on the div to invalid
					var submitBtn = document.getElementById("submit_button");
					submitBtn.disabled = true;
				}
				else
				{
					var field = document.getElementById(string);
					var validate = document.getElementById(\'validate_\' + string);
					field.className = "valid_field";
					validate.innerHTML = "', $this->lng->get('validated') , '";
					var submitBtn = document.getElementById("submit_button");
					submitBtn.disabled = false;
				}
			}
		</script>
		<style type="text/css">
			body
			{
				background-color: #cbd9e7;
				margin: 0px;
				padding: 0px;
			}
			body, td
			{
				color: #000;
				font-size: small;
				font-family: arial;
			}
			a
			{
				color: #2a4259;
				text-decoration: none;
				border-bottom: 1px dashed #789;
			}
			#header
			{
				background-color: #809ab3;
				padding: 22px 4% 12px 4%;
				color: #fff;
				text-shadow: 0 0 8px #333;
				font-size: xx-large;
				border-bottom: 1px solid #fff;
				height: 40px;
			}
			#main
			{
				padding: 20px 30px;
				background-color: #fff;
				border-radius: 5px;
				margin: 7px;
				border: 1px solid #abadb3;
			}
			#path_from, #path_to
			{
				width: 480px;
			}
			.error_message, blockquote, .error
			{
				border: 1px dashed red;
				border-radius: 5px;
				background-color: #fee;
				padding: 1.5ex;
			}
			.error_text
			{
				color: red;
			}
			.content
			{
				border-radius: 3px;
				background-color: #eee;
				color: #444;
				margin: 1ex 0;
				padding: 1.2ex;
				border: 1px solid #abadb3;
			}
			.button
			{
				margin: 0 0.8em 0.8em 0.8em;
			}
			#submit_button
			{
				cursor: pointer;
			}
			h1
			{
				margin: 0;
				padding: 0;
				font-size: 24pt;
			}
			h2
			{
				font-size: 15pt;
				color: #809ab3;
				font-weight: bold;
			}
			form
			{
				margin: 0;
			}
			.textbox
			{
				padding-top: 2px;
				white-space: nowrap;
				padding-right: 1ex;
			}
			.bp_invalid
			{
				color:red;
				font-weight: bold;
			}
			.bp_valid
			{
				color:green;
			}
			.validate
			{
				font-style: italic;
				font-size: smaller;
			}
			.valid_field
			{
				background-color: #DEFEDD;
				border: 1px solid green;
			}
			.invalid_field
			{
				background-color: #fee;;
				border: 1px solid red;
			}
			#progressbar
			{
				position: relative;
				top: -28px;
				left: 255px;
			}
			progress
			{
				width: 300px;
			}
			dl
			{
				clear: right;
				overflow: auto;
				margin: 0 0 0 0;
				padding: 0;
			}
			dt
			{
				width: 20%;
				float: left;
				margin: 6px 5px 10px 0;
				padding: 0;
				clear: both;
			}
			dd
			{
				width: 78%;
				float: right;
				margin: 6px 0 3px 0;
				padding: 0;
			}
			#arrow_up
			{
				display: none;
			}
			#toggle_button
			{
				display: block;
				color: #2a4259;
				margin-bottom: 4px;
				cursor: pointer;
			}
			.arrow
			{
				font-size: 8pt;
			}
			#destinations, #source
			{
				padding: 0 1em;
			}
			#destinations li label, #source li label
			{
				display: block;
				padding: 0.5em;
				cursor: pointer;
			}
			#destinations li, #source li
			{
				cursor: pointer;
				float: left;
				list-style: none;
				margin: 0 0.5em;
				border: 1px solid #abadb3;
				border-radius: 3px;
			}
			#destinations li, #source li
			{
				width: 12em;
				float: none;
				display: inline-block;
				cursor: default;
				vertical-align: middle;
				margin-top: 1em;
			}
			#destinations li.active, #source li.active
			{
				background-color: #fff;
			}
			#destinations ul:after, #source:after
			{
				content: "";
				display: block;
				clear: both;
			}
			.start_conversion
			{
				float: right;
			}
			.conversion:after
			{
				content: "";
				display: block;
				clear: both;
			}
		</style>
	</head>
	<body>
		<div id="header">
			<h1>', $this->response->page_title, '</h1>
		</div>
		<div id="main">';

		if (!empty($_GET['step']) && ($_GET['step'] == 1 || $_GET['step'] == 2) && (bool) $inner === true)
			echo '
			<h2 style="margin-top: 2ex">', $this->lng->get('importing'), '...</h2>
			<div class="content"><p>';
	}

	/**
	 * This is the template part for selecting the importer script.
	 *
	 * @param array $scripts
	 */
	public function selectScript($scripts, $destination_names)
	{
		echo '
			<h2>', $this->lng->get('to_what'), '</h2>
			<form class="conversion" action="', $_SERVER['PHP_SELF'], '" method="post">
				<div class="content">
					<p><label for="source">', $this->lng->get('locate_source'), '</label></p>
					<ul id="source">';

		foreach ($destination_names as $key => $value)
		{
			$id = preg_replace('~[^\w\d]~', '_', $key);
			echo '
						<li>
							<input class="input_select" data-type="destination" type="radio" value="', $key, '" id="destination_', $id, '" name="destination" />
							<label for="destination_', $id, '">', $value, '</label>
						</li>';
		}

		echo '
					</ul>
				</div>';

		echo '
				<h2>', $this->lng->get('which_software'), '</h2>
				<div class="content">';

		// We found at least one?
		if (!empty($scripts))
		{
			echo '
					<p>', $this->lng->get('multiple_files'), '</p>
					<ul id="destinations">';

			// Let's loop and output all the found scripts.
			foreach ($scripts as $key => $script)
			{
				$id = preg_replace('~[^\w\d]~', '_', $key);
				echo '
						<li>
							<input class="input_select" data-type="source" type="radio" value="', $script['path'], '" id="source_', $id, '" name="source" />
							<label for="source_', $id, '">', $script['name'], '</label>
						</li>';
			}

			echo '
					</ul>
				</div>
				<input class="start_conversion" type="submit" value="', $this->lng->get('start_conversion'), '" />
			</form>
			<h2>', $this->lng->get('not_here'), '</h2>
			<div class="content">
				<p>', $this->lng->get('check_more'), '</p>
				<p>', $this->lng->get('having_problems'), '</p>';
		}
		else
			echo '
				<p>', $this->lng->get('not_found'), '</p>
				<p>', $this->lng->get('not_found_download'), '</p>
				<a href="', $this->response->scripturl, '?import_script=">', $this->lng->get('try_again'), '</a>';

		echo '
			</div>
			<script>
				$(document).ready(function() {
					$(".input_select").each(function() {
						var $input = $(this),
							$button = $input.next();

						$button.click(function() {
							var $elem = $(this),
								type = $input.data("type");

							$(".input_select").each(function() {
								if ($(this).data("type") == type)
								{
									if ($(this).val() == $input.val())
										return true;

									$input.prop("checked", false);
									$(this).closest("li").removeClass("active");
								}
							});
							$input.prop("checked", !$input.prop("checked"));
							$(this).closest("li").toggleClass("active");
						});

						$input.hide();
						$input.after($button);
					});
				});
			</script>';
	}

	public function step0(ImportManager $object, Form $form)
	{
		echo '
			<h2>', $this->lng->get('before_continue'), '</h2>
			<div class="content">
				<p>', sprintf($this->lng->get('before_details'), (string) $object->importer->xml->general->name, (string) $object->config->destination->scriptname ), '</p>
			</div>';
		$form->title = $this->lng->get('where');
		$form->description = $this->lng->get('locate_destination');
		$form->submit = array(
			'name' => 'submit_button',
			'value' => $this->lng->get('continue'),
		);
		$this->renderForm($form);

		echo '
			<div class="content">
				<h3>', $this->lng->get('not_this'),'</h3>
				<p>', sprintf($this->lng->get('pick_different'), $_SERVER['PHP_SELF']), '</p>
			</div>';
	}

	public function emptyPage()
	{
	}

	/**
	 * Display notification with the given status
	 *
	 * @param int $substep
	 * @param int $status
	 * @param string $title
	 * @param bool $hide = false
	 */
	public function status($substep, $status, $title, $hide = false)
	{
		if (isset($title) && (bool) $hide === false)
			echo '<span style="width: 250px; display: inline-block">' . $title . '...</span> ';

		if ($status == 1)
			echo '<span style="color: green">&#x2714</span>';

		if ($status == 2)
			echo '<span style="color: grey">&#x2714</span> (', $this->lng->get('skipped'),')';

		if ($status == 3)
			echo '<span style="color: red">&#x2718</span> (', $this->lng->get('not_found_skipped'),')';

		if ($status != 0)
			echo '<br />';
	}

	/**
	 * Display information related to step2
	 */
	public function step2()
	{
		echo '
				<span style="width: 250px; display: inline-block">', $this->lng->get('recalculate'), '...</span> ';
	}

	/**
	 * Display last step UI, completion status and allow eventually
	 * to delete the scripts
	 *
	 * @param string $name
	 * @param string $boardurl
	 * @param bool $writable if the files are writable, the UI will allow deletion
	 */
	public function step3($name, $boardurl, $writable)
	{
		echo '
			</div>
			<h2 style="margin-top: 2ex">', $this->lng->get('complete'), '</h2>
			<div class="content">
			<p>', $this->lng->get('congrats'),'</p>';

		if ($writable)
			echo '
				<div style="margin: 1ex; font-weight: bold">
					<label for="delete_self"><input type="checkbox" id="delete_self" onclick="doTheDelete()" />', $this->lng->get('check_box'), '</label>
				</div>
				<script type="text/javascript"><!-- // --><![CDATA[
					function doTheDelete()
					{
						new Image().src = "', $_SERVER['PHP_SELF'], '?delete=1&" + (+Date());
						(document.getElementById ? document.getElementById("delete_self") : document.all.delete_self).disabled = true;
					}
				// ]]></script>';
		echo '
				<p>', sprintf($this->lng->get('all_imported'), $name), '</p>
				<p>', $this->lng->get('smooth_transition'), '</p>';
	}

	/**
	 * Display the progress bar,
	 * and inform the user about when the script is paused and re-run.
	 *
	 * @param int $bar
	 * @param int $value
	 * @param int $max
	 */
	public function timeLimit($bar, $value, $max)
	{
		if (!empty($bar))
			echo '
			<div id="progressbar">
				<progress value="', $bar, '" max="100">', $bar, '%</progress>
			</div>';

		echo '
		</div>
		<h2 style="margin-top: 2ex">', $this->lng->get('not_done'),'</h2>
		<div class="content">
			<div style="margin-bottom: 15px; margin-top: 10px;"><span style="width: 250px; display: inline-block">', $this->lng->get('overall_progress'),'</span><progress value="', $value, '" max="', $max, '"></progress></div>
			<p>', $this->lng->get('importer_paused'), '</p>

			<form action="', $_SERVER['PHP_SELF'], '?step=', $_GET['step'], isset($_GET['substep']) ? '&amp;substep=' . $_GET['substep'] : '', '&amp;start=', $_REQUEST['start'], '" method="post" name="autoSubmit">
				<div align="right" style="margin: 1ex"><input name="b" type="submit" value="', $this->lng->get('continue'),'" /></div>
			</form>

			<script type="text/javascript"><!-- // --><![CDATA[
				var countdown = 3;
				window.onload = doAutoSubmit;

				function doAutoSubmit()
				{
					if (countdown == 0)
						document.autoSubmit.submit();
					else if (countdown == -1)
						return;

					document.autoSubmit.b.value = "', $this->lng->get('continue'),' (" + countdown + ")";
					countdown--;

					setTimeout("doAutoSubmit();", 1000);
				}
			// ]]></script>';
	}

	/**
	 * ajax response, whether the paths to the source and destination
	 * software are correctly set.
	 */
	public function xml()
	{
		echo '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
	<valid>', $this->response->valid ? 'true' : 'false' ,'</valid>';
	}

	public function renderForm(Form $form)
	{
		$toggle = false;

		echo '
			<h2>', $form->title, '</h2>
			<div class="content">
				<form action="', $form->action_url, '" method="post">
					<p>', $form->description, '</p>
					<dl>';

		foreach ($form->options as $option)
		{
			if (empty($option))
			{
				$toggle = true;
				echo '
					</dl>
					<div id="toggle_button">', $this->lng->get('advanced_options'), ' <span id="arrow_down" class="arrow">&#9660</span><span id="arrow_up" class="arrow">&#9650</span></div>
					<dl id="advanced_options" style="display: none; margin-top: 5px">';
				continue;
			}

			switch ($option['type'])
			{
				case 'text':
					echo '
						<dt><label for="', $option['id'], '">', $option['label'], ':</label></dt>
						<dd>
							<input type="text" name="', $option['id'], '" id="', $option['id'], '" value="', $option['value'], '" ', !empty($option['validate']) ? 'onblur="validateField(\'' . $option['id'] . '\')"' : '', ' class="text" />
							<div id="validate_', $option['id'], '" class="validate">', $option['correct'], '</div>
						</dd>';
					break;
				case 'checkbox':
					echo '
						<dt></dt>
						<dd>
							<label for="', $option['id'], '">', $option['label'], ':
								<input type="checkbox" name="', $option['id'], '" id="', $option['id'], '" value="', $option['value'], '" ', $option['attributes'], '/>
							</label>
						</dd>';
					break;
				case 'password':
					echo '
						<dt><label for="', $option['id'], '">', $option['label'], ':</label></dt>
						<dd>
							<input type="password" name="', $option['id'], '" id="', $option['id'], '" class="text" />
							<div style="font-style: italic; font-size: smaller">', $option['correct'], '</div>
						</dd>';
					break;
				case 'steps':
					echo '
						<dt><label for="', $option['id'], '">', $option['label'], ':</label></dt>
						<dd>';
						foreach ($option['value'] as $key => $step)
							echo '
							<label><input type="checkbox" name="do_steps[', $key, ']" id="do_steps[', $key, ']" value="', $step['count'], '"', $step['mandatory'] ? 'readonly="readonly" ' : ' ', $step['checked'], '" /> ', $step['label'], '</label><br />';

					echo '
						</dd>';
					break;
			}
		}

		echo '
					</dl>
					<div class="button"><input id="submit_button" name="', $form->submit['name'], '" type="submit" value="', $form->submit['value'],'" class="submit" /></div>
				</form>
			</div>';

		if ($toggle)
			echo '
			<script type="text/javascript">
				document.getElementById(\'toggle_button\').onclick = function ()
				{
					var elem = document.getElementById(\'advanced_options\');
					var arrow_up = document.getElementById(\'arrow_up\');
					var arrow_down = document.getElementById(\'arrow_down\');
					if (!elem)
						return true;

					if (elem.style.display == \'none\')
					{
						elem.style.display = \'block\';
						arrow_down.style.display = \'none\';
						arrow_up.style.display = \'inline\';
					}
					else
					{
						elem.style.display = \'none\';
						arrow_down.style.display = \'inline\';
						arrow_up.style.display = \'none\';
					}

					return true;
				}
			</script>';
	}
}