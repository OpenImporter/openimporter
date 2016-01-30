<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Core;

/**
 * Class Template
 * This is our UI
 *
 * @package OpenImporter\Core
 */
class Template
{
	/**
	 * @var HttpResponse
	 */
	protected $response;
	protected $replaces = array();
	protected $lng = null;
	protected $config = null;
	protected $header_rendered = false;

	/**
	 * Template constructor.
	 *
	 * @param Lang $lng
	 * @param Configurator $config
	 */
	public function __construct(Lang $lng, Configurator $config)
	{
		$this->lng = $lng;
		$this->config = $config;
	}

	/**
	 * Render a page to show
	 *
	 * @param null|HttpResponse $response
	 */
	public function render($response = null)
	{
		if ($response !== null)
		{
			$this->setResponse($response);
		}

		// No text? ... so sad. :(
		if ($this->response->no_template)
		{
			return;
		}

		$this->initReplaces();

		$this->response->styles = $this->fetchStyles();
		$this->response->scripts = $this->fetchScripts();

		$this->sendHead();

		$this->sendBody();

		$this->sendFooter();
	}

	/**
	 * Set the instance of the HttpResponse
	 *
	 * @param HttpResponse $response
	 */
	public function setResponse($response)
	{
		$this->response = $response;
	}

	protected function initReplaces()
	{
		$this->replaces = array();

		foreach ($this->response->getAll() as $key => $val)
		{
			if (!is_object($val) && !is_array($val))
			{
				$this->replaces['{{response->' . $key . '}}'] = (string) $val;
			}
		}

		foreach ($this->lng->getAll() as $key => $val)
		{
			if (!is_object($val) && !is_array($val))
			{
				$this->replaces['{{language->' . $key . '}}'] = $val;
			}
		}
	}

	/**
	 * Return any style sheets that need to be loaded with the template
	 *
	 * @return string
	 */
	protected function fetchStyles()
	{
		if (file_exists($this->response->assets_dir . '/index.css'))
		{
			return file_get_contents($this->response->assets_dir . '/index.css');
		}
		else
		{
			return '';
		}
	}

	/**
	 * Return any scripts that need to be loaded in the template
	 *
	 * @return string
	 */
	protected function fetchScripts()
	{
		if (file_exists($this->response->assets_dir . '/scripts.js'))
		{
			$file = file_get_contents($this->response->assets_dir . '/scripts.js');

			return strtr($file, $this->replaces);
		}
		else
		{
			return '';
		}
	}

	protected function sendHead()
	{
		if ($this->header_rendered === false)
		{
			$this->response->sendHeaders();

			if ($this->response->is_page)
			{
				$this->header();
			}

			$this->header_rendered = true;
		}
	}

	/**
	 * Show the header.
	 */
	public function header()
	{
		echo '<!DOCTYPE html>
<html lang="', $this->lng->get('locale'), '">
	<head>
		<meta charset="UTF-8" />
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<title>', $this->response->page_title, '</title>

		<script src="//code.jquery.com/jquery-2.2.0.min.js"></script>
		<script>
', $this->response->scripts, '
		</script>
		<style type="text/css">
', $this->response->styles, '
		</style>
	</head>
	<body>
		<div id="header">
			<h1>', $this->response->page_title, '</h1>
		</div>
		<div id="main">';

		if ($this->config->progress->current_step == 1 || $this->config->progress->current_step == 2)
		{
			echo '
			<h2>', $this->lng->get('importing'), '...</h2>
			<div class="content"><p>';
		}
	}

	/**
	 * Show the main body of the template
	 */
	protected function sendBody()
	{
		// Any errors that we should let them know about
		if ($this->response->is_page && $this->response->template_error)
		{
			$this->renderErrors();
		}

		$templates = $this->response->getTemplates();
		foreach ($templates as $template)
		{
			call_user_func_array(array($this, $template['name']), $template['params']);
		}
	}

	/**
	 * If we have errors, this will show them
	 */
	protected function renderErrors()
	{
		foreach ($this->response->getErrors() as $msg)
		{
			if (is_array($msg))
			{
				call_user_func_array(array($this, 'error'), $msg);
			}
			else
			{
				$this->error($msg);
			}
		}
	}

	/**
	 * Display a specific error message.
	 *
	 * @param string $error_message
	 * @param int|bool $trace
	 * @param int|bool $line
	 * @param string|bool $file
	 */
	public function error($error_message, $trace = false, $line = false, $file = false, $query = false)
	{
		echo '
			<div class="error_message">
				<div class="error_text">';
		if ($query)
		{
			echo '
				<b>', $this->lng->get('db_unsuccessful'), '</b><br />
				', $this->lng->get(array('db_query_failed', '<blockquote>' . $query['query'] . '</blockquote>')), '
				', $this->lng->get(array('db_error_caused', '<br />
				<blockquote>' . $query['error'] . '</blockquote>')), '
				<form action="' . $query['action_url'] . '" method="post">
					<input type="submit" value="', $this->lng->get('try_again'), '" />
				</form>';
		}
		else
		{
			echo '
					', !empty($trace) ? $this->lng->get(array('error_message', $error_message)) : $error_message;
		}

		echo '
				</div>';

		if (!empty($trace))
		{
			echo '
				<div class="error_text">', $this->lng->get(array('error_trace', $trace)), '</div>';
		}

		if (!empty($line))
		{
			echo '
				<div class="error_text">', $this->lng->get(array('error_line', $line)), '</div>';
		}

		if (!empty($file))
		{
			echo '
				<div class="error_text">', $this->lng->get(array('error_file', $file)), '</div>';
		}

		echo '
			</div>';
	}

	protected function sendFooter()
	{
		if ($this->response->is_page)
		{
			$this->footer();
		}
	}

	/**
	 * Show the footer.
	 */
	public function footer()
	{
		if ($this->response->step == 1 || $this->response->step == 2)
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
	 * This is the template part for selecting the source and destination systems
	 *
	 * @param array $scripts
	 * @param array $destination_names
	 */
	public function selectScript($scripts, $destination_names)
	{
		echo '
			<h2>', $this->lng->get('to_what'), '</h2>
			<form id="conversion" class="conversion" action="', $this->response->scripturl, '" method="post">
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
		{
			echo '
				<p>', $this->lng->get('not_found'), '</p>
				<p>', $this->lng->get('not_found_download'), '</p>
				<a href="', $this->response->scripturl, '?action=reset">', $this->lng->get('try_again'), '</a>';
		}

		echo '
			</div>';
	}

	/**
	 * @param Form $form
	 */
	public function step0(Form $form)
	{
		echo '
			<h2>', $this->lng->get('before_continue'), '</h2>
			<div class="content">
				<p>', sprintf($this->lng->get('before_details'), $this->response->source_name, $this->response->destination_name), '</p>
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
				<h3>', $this->lng->get('not_this'), '</h3>
				<p>', $this->lng->get(array('pick_different', $this->response->scripturl . '?action=reset')), '</p>
			</div>';
	}

	public function renderForm(Form $form)
	{
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
				echo '
					</dl>
					<div id="toggle_button" class="open">', $this->lng->get('advanced_options'), '</div>
					<dl id="advanced_options">';
				continue;
			}

			switch ($option['type'])
			{
				case 'text':
					echo '
						<dt><label for="', $option['id'], '">', $option['label'], ':</label></dt>
						<dd>
							<input type="text" name="', $option['id'], '" id="', $option['id'], '" value="', $option['value'], '" class="text', !empty($option['validate']) ? ' dovalidation' : '', '" />
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
							<div class="passwdcheck">', $option['correct'], '</div>
						</dd>';
					break;
				case 'steps':
					echo '
						<dt><label for="', $option['id'], '">', $option['label'], ':</label></dt>
						<dd>';
					foreach ($option['value'] as $key => $step)
					{
						echo '
							<label><input type="checkbox" name="do_steps[', $key, ']" id="do_steps[', $key, ']" value="', $step['count'], '"', $step['mandatory'] ? ' readonly="readonly" ' : ' ', $step['checked'], ' /> ', $step['label'], '</label><br />';
					}

					echo '
						</dd>';
					break;
			}
		}

		echo '
					</dl>
					<div class="button">
						<input id="submit_button" name="', $form->submit['name'], '" type="submit" value="', $form->submit['value'], '" class="submit" />
					</div>
				</form>
			</div>';
	}

	/**
	 * Display last step UI, completion status and allow eventually
	 * to delete the scripts
	 *
	 * @param string $name
	 * @param bool $writable if the files are writable, the UI will allow deletion
	 */
	public function step3($name, $writable)
	{
		echo '
			</div>
			<h2>', $this->lng->get('complete'), '</h2>
			<div class="content">
			<p>', $this->lng->get('congrats'), '</p>';

		if ($writable)
		{
			echo '
				<div class="notice">
					<label for="delete_self"><input type="checkbox" id="delete_self" />', $this->lng->get('check_box'), '</label>
				</div>';
		}

		echo '
				<p>', sprintf($this->lng->get('all_imported'), $name), '</p>
				<p>', $this->lng->get('smooth_transition'), '</p>';
	}

	/**
	 * Display the progress bar,
	 * and inform the user about when the script is paused and re-run.
	 *
	 * @todo the url should be built in the PasttimeException, not here
	 *
	 * @param int $bar
	 * @param int $value
	 * @param int $max
	 * @param int $substep
	 * @param int $start
	 */
	public function timeLimit($bar, $value, $max, $substep, $start)
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
		<h2>', $this->lng->get('not_done'), '</h2>
		<div class="content">
			<div class="progress"><span>', $this->lng->get('overall_progress'), '</span><progress value="', $value, '" max="', $max, '"></progress></div>
			<p>', $this->lng->get('importer_paused'), '</p>

			<form action="', $this->response->scripturl, '?step=', $this->response->step, '&amp;substep=', $substep, '&amp;start=', $start, '" method="post" name="autoSubmit">
				<div class="continue"><input name="b" type="submit" value="', $this->lng->get('continue'), '" /></div>
			</form>

			<script>
				var countdown = 3;
				window.onload = doAutoSubmit;
			</script>';
	}

	/**
	 * ajax response, whether the paths to the source and destination
	 * software are correctly set.
	 */
	public function validate()
	{
		echo '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
	<valid>', $this->response->valid ? 'true' : 'false', '</valid>';
	}

	protected function renderStatuses()
	{
		echo '
		<span class="statuses">';

		foreach ($this->response->getStatuses() as $status)
		{
			$this->status($status['status'], $status['title']);
		}

		echo '
		</span>';
	}

	/**
	 * Display notification with the given status
	 *
	 * @param int $status
	 * @param string $title
	 */
	public function status($status, $title)
	{
		if (!empty($title))
		{
			echo '<span class="text">' . $title . '...</span> ';
		}

		if ($status == 1)
		{
			echo '<span class="success">&#x2714</span>';
		}

		if ($status == 2)
		{
			echo '<span class="disabled">&#x2714</span> (', $this->lng->get('skipped'), ')';
		}

		if ($status == 3)
		{
			echo '<span class="failure">&#x2718</span> (', $this->lng->get('not_found_skipped'), ')';
		}

		if ($status != 0)
		{
			echo '<br />';
		}
	}
}