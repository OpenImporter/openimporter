/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

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
	var url = "import.php?action=validate&xml=true&" + string + "=" + target.value.replace(/\/+$/g, "") + "&source={{response->source}}&destination={{response->destination}}";
	var ajax = new AJAXCall(url, validateCallback, string);
	ajax.doGet();
}

function doAutoSubmit()
{
	if (countdown == 0)
		document.autoSubmit.submit();
	else if (countdown == -1)
		return;

	document.autoSubmit.b.value = "{{language->continue}} (" + countdown + ")";
	countdown--;

	setTimeout("doAutoSubmit();", 1000);
}

function validateCallback(responseXML, string)
{
	var msg = responseXML.getElementsByTagName("valid")[0].firstChild.nodeValue;
	if (msg == "false")
	{
		var field = document.getElementById(string);
		var validate = document.getElementById('validate_' + string);
		field.className = "invalid_field";
		validate.innerHTML = "{{language->invalid}}";
		// set the style on the div to invalid
		var submitBtn = document.getElementById("submit_button");
		submitBtn.disabled = true;
	}
	else
	{
		var field = document.getElementById(string);
		var validate = document.getElementById('validate_' + string);
		field.className = "valid_field";
		validate.innerHTML = "{{language->validated}}";
		var submitBtn = document.getElementById("submit_button");
		submitBtn.disabled = false;
	}
}

function doTheDelete()
{
	new Image().src = "{{response->scripturl}}?action=delete&" + (+Date());
	(document.getElementById ? document.getElementById("delete_self") : document.all.delete_self).disabled = true;
}

$(document).ready(function() {
	$('#toggle_button').click(function () {
		var $elem = $('#advanced_options');

		if ($elem.is(':visible'))
		{
			$elem.slideUp('fast');
			$(this).removeClass('close').addClass('open');
		}
		else
		{
			$elem.slideDown('fast');
			$(this).removeClass('open').addClass('close');
		}

		return true;
	});

	$("#conversion .input_select").each(function() {
		var $input = $(this),
			$button = $input.next();

		$button.click(function() {
			var $elem = $(this),
				type = $input.data("type");

			$("#conversion .input_select").each(function() {
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