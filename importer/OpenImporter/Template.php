<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

namespace OpenImporter;

/**
 * Class Template
 * This is our UI
 *
 * @package OpenImporter
 */
class Template
{
	/**
	 * @var null|HttpResponse
	 */
	protected $response = null;

	/**
	 * @var null|DummyLang
	 */
	protected $language = null;

	/**
	 * Template constructor.
	 *
	 * @param $language
	 */
	public function __construct($language)
	{
		// If nothing is found, use a stub
		if ($language === null)
		{
			$language = new DummyLang();
		}

		$this->language = $language;
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
					', !empty($trace) ? $this->language->get(array('error_message', $error_message)) : $error_message, '
				</div>';

		if (!empty($trace))
		{
			echo '
				<div class="error_text">', $this->language->get(array('error_trace', $trace)), '</div>';
		}

		if (!empty($line))
		{
			echo '
				<div class="error_text">', $this->language->get(array('error_line', $line)), '</div>';
		}

		if (!empty($file))
		{
			echo '
				<div class="error_text">', $this->language->get(array('error_file', $file)), '</div>';
		}

		echo '
			</div>';
	}

	/**
	 * Sets the response for the template to use
	 *
	 * @param HttpResponse $response
	 */
	public function setResponse($response)
	{
		$this->response = $response;
	}

	/**
	 * Renders the template
	 */
	public function render()
	{
		// No text? ... so sad. :(
		if ($this->response->no_template)
		{
			return;
		}

		// Set http headers as needed
		$this->response->sendHeaders();

		// XML ajax feedback? We can just skip everything else
		if ($this->response->is_xml)
		{
			$this->xml();
		}
		// Maybe showing a new page
		elseif ($this->response->is_page)
		{
			// Header
			$this->header(!$this->response->template_error);

			// Body
			if ($this->response->template_error)
			{
				foreach ($this->response->getErrors() as $msg)
				{
					$this->error($msg);
				}
			}

			call_user_func_array(array($this, $this->response->use_template), $this->response->params_template);

			// Footer
			$this->footer(!$this->response->template_error);
		}
		else
		{
			call_user_func_array(array($this, $this->response->use_template), $this->response->params_template);
		}
	}

	/**
	 * Show the footer.
	 *
	 * @param bool $inner
	 */
	public function footer($inner = true)
	{
		if (($this->response->step == 1 || $this->response->step == 2) && $inner == true)
		{
			echo '
				</p>
			</div>';
		}
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
<html lang="', $this->language->get('locale'), '">
	<head>
		<meta charset="UTF-8" />
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<title>', $this->response->page_title, '</title>
		<script>
			function AJAXCall(url, callback, string)
			{
				var req = new XMLHttpRequest(),
					string = string;

				req.onreadystatechange = processRequest;

				function processRequest()
				{
					// ReadyState of 4 signifies request is complete
					if (req.readyState == 4)
					{
						// Status of 200 signifies successful HTTP call
						if (req.status == 200)
							if (callback) callback(req.responseXML, string);
					}
				}

				// Make a HTTP GET request to the URL asynchronously
				this.doGet = function () {
					req.open("GET", url, true);
					req.send(null);
				};
			}
			function validateField(string)
			{
				var target = document.getElementById(string),
					url = "import.php?action=validate&xml=true&" + string + "=" + target.value.replace(/\/+$/g, "") + "&import_script=' . addslashes($this->response->script) . '",
					ajax = new AJAXCall(url, validateCallback, string);

				ajax.doGet();
			}

			function validateCallback(responseXML, string)
			{
				var msg = responseXML.getElementsByTagName("valid")[0].firstChild.nodeValue,
					field = document.getElementById(string),
					validate = document.getElementById(\'validate_\' + string),
					submitBtn = document.getElementById("submit_button");

				if (msg == "false")
				{
					field.className = "invalid_field";
					validate.innerHTML = "' . $this->language->get('invalid') . '";

					// Set the style on the div to invalid
					submitBtn.disabled = true;
				}
				else
				{
					field.className = "valid_field";
					validate.innerHTML = "' . $this->language->get('validated') . '";

					submitBtn.disabled = false;
				}
			}
		</script>
		<style type="text/css">
			body {
				background-color: #cbd9e7;
				margin: 0;
				padding: 0;
			}
			body, td {
				color: #000;
				font-size: small;
				font-family: arial;
			}
			a {
				color: #2a4259;
				text-decoration: none;
				border-bottom: 1px dashed #789;
			}
			#header {
				background-color: #809ab3;
				padding: 22px 4% 12px 4%;
				color: #fff;
				text-shadow: 0 0 8px #333;
				border-bottom: 1px solid #fff;
				height: 40px;
			}
			#main {
				padding: 20px 30px;
				background-color: #fff;
				border-radius: 5px;
				margin: 7px;
				border: 1px solid #abadb3;
			}
			#path_from, #path_to {
				width: 480px;
			}
			.error_message, blockquote, .error {
				border: 1px dashed red;
				border-radius: 5px;
				background-color: #fee;
				padding: 1.5ex;
			}
			.error_text {
				color: red;
			}
			.content {
				border-radius: 3px;
				background-color: #eee;
				color: #444;
				margin: 1ex 0;
				padding: 1.2ex;
				border: 1px solid #abadb3;
			}
			.button {
				margin: 0 0.8em 0.8em 0.8em;
			}
			#submit_button {
				cursor: pointer;
			}
			h1 {
				margin: 0;
				padding: 0;
				font-size: 2.5em;
			}
			h2 {
				font-size: 1.5em;
				color: #809ab3;
				font-weight: bold;
			}
			form {
				margin: 0;
			}
			.textbox {
				padding-top: 2px;
				white-space: nowrap;
				padding-right: 1ex;
			}
			.bp_invalid {
				color:red;
				font-weight: bold;
			}
			.bp_valid {
				color:green;
			}
			.validate {
				font-style: italic;
				font-size: smaller;
			}
			.valid_field {
				background-color: #DEFEDD;
				border: 1px solid green;
			}
			.invalid_field {
				background-color: #fee;;
				border: 1px solid red;
			}
			#progressbar {
				position: relative;
				top: -28px;
				left: 255px;
			}
			progress {
				width: 300px;
			}
			#advanced_options {
			 	-moz-columns: 2;
  				-webkit-columns: 2;
  				columns: 2;
  				margin-left: 20%;
			}
			#advanced_options dt {
  				-moz-page-break-after: avoid;
				-webkit-column-break-after: avoid;
				break-after: avoid;
				width: 50%;
				float: none;
			}
			#advanced_options dd {
				-moz-page-break-before: avoid;
				-webkit-column-break-before: avoid;
				break-before: avoid;
				float: none;
			}
			dl {
				clear: right;
				overflow: auto;
				margin: 0 0 0 0;
				padding: 0;
			}
			dt {
				width: 20%;
				float: left;
				margin: 6px 5px 10px 0;
				padding: 0;
				clear: both;
			}
			dd {
				width: 78%;
				float: right;
				margin: 6px 0 3px 0;
				padding: 0;
			}
			#arrow_up {
				display: none;
			}
			#toggle_button {
				display: block;
				color: #2a4259;
				margin-bottom: 4px;
				cursor: pointer;
			}
			.arrow {
				font-size: 8pt;
			}
			#destinations ul, #source {
				padding: 0 1em;
			}
			#destinations ul li a {
				display: block;
				margin-bottom: 3px;
				padding-bottom: 3px;
			}
			#destinations ul li, #source li {
				cursor: pointer;
				float: left;
				list-style: none;
				padding: 0.5em;
				margin: 0 0.5em;
				border: 1px solid #abadb3;
				border-radius: 3px;
			}
			#destinations ul li {
				width: 20%;
				float: none;
				display: inline-block;
				height: 4em;
				cursor: default;
				vertical-align: middle;
				margin-top: 1em;
			}
			#destinations ul li.active, #source li.active {
				background-color: #fff;
			}
			#destinations ul:after, #source:after {
				content: "";
				display: block;
				clear: both;
			}
		</style>
	</head>
	<body>
		<div id="header">
			<h1>', isset($this->response->importer->xml->general->{'name'}) ? $this->response->importer->xml->general->{'name'} . ' to ' : '', 'OpenImporter</h1>
		</div>
		<div id="main">';

		if (!empty($_GET['step']) && ($_GET['step'] == 1 || $_GET['step'] == 2) && $inner == true)
		{
			echo '
			<h2 style="margin-top: 2ex">', $this->language->get('importing'), '...</h2>
			<div class="content"><p>';
		}
	}

	/**
	 * This is the template part for selecting the importer script.
	 *
	 * @param array $scripts The available "From" forums
	 * @param string[] $destination_names The available "To" forums
	 */
	public function select_script($scripts, $destination_names)
	{
		echo '
			<h2>', $this->language->get('to_what'), '</h2>
			<div class="content">
				<p><label for="source">', $this->language->get('locate_source'), '</label></p>
				<ul id="source">';

		// Who can we import into?
		foreach ($destination_names as $key => $values)
		{
			echo '
					<li onclick="toggle_to(this);" data-value="', preg_replace('~[^\w\d]~', '_', $key), '">', $values, '</li>';
		}

		echo '
				</ul>
			</div>';

		echo '
			<h2>', $this->language->get('which_software'), '</h2>
			<div id="destinations" class="content">';

		// We found at least one?
		if (!empty($scripts))
		{
			echo '
				<p>', $this->language->get('multiple_files'), '</p>';

			foreach ($scripts as $key => $value)
			{
				echo '
				<ul id="', preg_replace('~[^\w\d]~', '_', $key), '">';

				// Let's loop and output all the found scripts.
				foreach ($value as $script)
				{
					echo '
					<li>
						<a href="', $_SERVER['PHP_SELF'], '?import_script=', $script['path'], '">', $script['name'], '</a>
						<span>(', $script['path'], ')</span>
					</li>';
				}

				echo '
				</ul>';
			}

			echo '
			</div>
			<h2>', $this->language->get('not_here'), '</h2>
			<div class="content">
				<p>', $this->language->get('check_more'), '</p>
				<p>', $this->language->get('having_problems'), '</p>';
		}
		else
		{
			echo '
				<p>', $this->language->get('not_found'), '</p>
				<p>', $this->language->get('not_found_download'), '</p>
				<a href="', $_SERVER['PHP_SELF'], '?import_script=">', $this->language->get('try_again'), '</a>';
		}

		echo '
			<script>
				function toggle_to(e)
				{
					var dest_container = document.getElementById(\'destinations\'),
						dests = dest_container.getElementsByTagName(\'ul\'),
						sources = document.getElementById(\'source\').getElementsByTagName(\'li\'),
						i;

					for (i = 0; i < dests.length; i++)
						dests[i].style.display = \'none\';

					if (typeof e === \'undefined\')
						e = sources[0];

					for (i = 0; i < sources.length; i++)
						sources[i].removeAttribute("class");

					e.setAttribute("class", "active");
					document.getElementById(e.getAttribute(\'data-value\')).style.display = \'block\';
				}

				toggle_to();
			</script>
			</div>';
	}

	/**
	 * Everyone has to start somewhere, we start and 0,0,0,0
	 *
	 * Called from doStep0 from the ImportManager
	 *
	 * @param ImportManager $object
	 * @param Form $form
	 */
	public function step0($object, $form)
	{
		echo '
			<h2>', $this->language->get('before_continue'), '</h2>
			<div class="content">
				<p>', sprintf($this->language->get('before_details'), (string) $object->importer->xml->general->name), '</p>
			</div>';

		$form->title = $this->language->get('where');
		$form->description = $this->language->get('locate_destination');
		$form->submit = array(
			'name' => 'submit_button',
			'value' => $this->language->get('continue'),
		);

		$this->renderForm($form);

		if (!empty($object->possible_scripts))
		{
			echo '
			<h2>', $this->language->get('not_this'), '</h2>
			<div class="content">
				<p>', sprintf($this->language->get('pick_different'), $_SERVER['PHP_SELF']), '</p>
			</div>';
		}
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
		if (isset($title) && $hide == false)
		{
			echo '<span style="width: 250px; display: inline-block">' . $title . '...</span> ';
		}

		if ($status == 1)
		{
			echo '<span style="color: green">&#x2714</span>';
		}

		if ($status == 2)
		{
			echo '<span style="color: grey">&#x2714</span> (', $this->language->get('skipped'), ')';
		}

		if ($status == 3)
		{
			echo '<span style="color: red">&#x2718</span> (', $this->language->get('not_found_skipped'), ')';
		}

		if ($status != 0)
		{
			echo '<br />';
		}
	}

	/**
	 * Display information related to step2
	 */
	public function step2()
	{
		echo '
				<span style="width: 250px; display: inline-block">', $this->language->get('recalculate'), '...</span> ';
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
			<h2 style="margin-top: 2ex">', $this->language->get('complete'), '</h2>
			<div class="content">
			<p>', $this->language->get('congrats'), '</p>';

		if ($writable)
		{
			echo '
				<div style="margin: 1ex; font-weight: bold">
					<label for="delete_self"><input type="checkbox" id="delete_self" onclick="doTheDelete()" />', $this->language->get('check_box'), '</label>
				</div>
				<script>
					function doTheDelete()
					{
						new Image().src = "', $_SERVER['PHP_SELF'], '?delete=1&" + (+Date());
						(document.getElementById ? document.getElementById("delete_self") : document.all.delete_self).disabled = true;
					}
				</script>';
		}

		echo '
				<p>', sprintf($this->language->get('all_imported'), $name), '</p>
				<p>', $this->language->get('smooth_transition'), '</p>';
	}

	/**
	 * Display the progress bar,
	 * and inform the user about when the script is paused and re-run.
	 *
	 * @param int $bar
	 * @param int $value
	 * @param int $max
	 */
	public function time_limit($bar, $value, $max)
	{
		if (!empty($bar))
		{
			echo '
			<div id="progressbar">
				<progress value="', $bar, '" max="100">', $bar, '%</progress>
			</div>';
		}

		echo '
		</div>
		<h2 style="margin-top: 2ex">', $this->language->get('not_done'), '</h2>
		<div class="content">
			<div style="margin-bottom: 15px; margin-top: 10px;"><span style="width: 250px; display: inline-block">', $this->language->get('overall_progress'), '</span><progress value="', $value, '" max="', $max, '"></progress></div>
			<p>', $this->language->get('importer_paused'), '</p>

			<form action="', $_SERVER['PHP_SELF'], '?step=', $_GET['step'], isset($_GET['substep']) ? '&amp;substep=' . $_GET['substep'] : '', '&amp;start=', $_REQUEST['start'], '" method="post" name="autoSubmit">
				<div align="right" style="margin: 1ex"><input name="b" type="submit" value="', $this->language->get('continue'), '" /></div>
			</form>

			<script>
				var countdown = 3;
				window.onload = doAutoSubmit;

				function doAutoSubmit()
				{
					if (countdown == 0)
						document.autoSubmit.submit();
					else if (countdown == -1)
						return;

					document.autoSubmit.b.value = "', $this->language->get('continue'), ' (" + countdown + ")";
					countdown--;

					setTimeout("doAutoSubmit();", 1000);
				}
			</script>';
	}

	/**
	 * Ajax response, whether the paths to the source and destination
	 * software are correctly set.
	 */
	public function xml()
	{
		echo '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
	<valid>', $this->response->valid ? 'true' : 'false', '</valid>';
	}

	/**
	 * Function to generate a form from a set of form options
	 * @param $form
	 */
	public function renderForm($form)
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
					<div id="toggle_button">', $this->language->get('advanced_options'), ' <span id="arrow_down" class="arrow">&#9660</span><span id="arrow_up" class="arrow">&#9650</span></div>
					<dl id="advanced_options" style="display: none; margin-top: 5px">';
				continue;
			}

			switch ($option['type'])
			{
				case 'text':
				{
					echo '
						<dt>
							<label for="', $option['id'], '">', $option['label'], ':</label>
						</dt>
						<dd>
							<input type="text" name="', $option['id'], '" id="', $option['id'], '" value="', $option['value'], '" ', !empty($option['validate']) ? 'onblur="validateField(\'' . $option['id'] . '\')"' : '', ' class="text" />
							<div id="validate_', $option['id'], '" class="validate">', $option['correct'], '</div>
						</dd>';
					break;
				}
				case 'checkbox':
				{
					echo '
						<dt></dt>
						<dd>
							<label for="', $option['id'], '">', $option['label'], ':
								<input type="checkbox" name="', $option['id'], '" id="', $option['id'], '" value="', $option['value'], '" ', $option['attributes'], '/>
							</label>
						</dd>';
					break;
				}
				case 'password':
				{
					echo '
						<dt>
							<label for="', $option['id'], '">', $option['label'], ':</label>
						</dt>
						<dd>
							<input type="password" name="', $option['id'], '" id="', $option['id'], '" class="text" />
							<div style="font-style: italic; font-size: smaller">', $option['correct'], '</div>
						</dd>';
					break;
				}
				case 'steps':
				{
					echo '
						<dt>
							<label for="', $option['id'], '">', $option['label'], ':</label>
						</dt>
						<dd>';

					foreach ($option['value'] as $key => $step)
					{
						echo '
							<label>
								<input type="checkbox" name="do_steps[', $key, ']" id="do_steps[', $key, ']" value="', $step['count'], '"', $step['mandatory'] ? 'readonly="readonly" ' : ' ', $step['checked'], '" /> ', $step['label'], '
							</label><br />';
					}

					echo '
						</dd>';
					break;
				}
			}
		}

		echo '
					</dl>
					<div class="button">
						<input id="submit_button" name="', $form->submit['name'], '" type="submit" value="', $form->submit['value'], '" class="submit" />
					</div>
				</form>
			</div>';

		if ($toggle)
		{
			echo '
			<script>
				document.getElementById(\'toggle_button\').onclick = function ()
				{
					var elem = document.getElementById(\'advanced_options\'),
						arrow_up = document.getElementById(\'arrow_up\'),
						arrow_down = document.getElementById(\'arrow_down\');

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
}