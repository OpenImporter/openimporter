/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

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

function doTheDelete()
{
	new Image().src = "{{response->scripturl}}?action=delete&" + (+Date());
	(document.getElementById ? document.getElementById("delete_self") : document.all.delete_self).disabled = true;
}

$(document).ready(function() {
	$('.dovalidation').change(function() {
		var data = {
			xml: 'xml',
			source: '{{response->source}}',
			destination: '{{response->destination}}'
		},
		$elem = $(this),
		string = $(this).attr('id');

		data[string] = $(this).val().replace(/\/+$/g, "");

		$.ajax({
			type: 'POST',
			url: 'import.php?action=validate',
			data: data
		})
		.done(function (request) {
			var validate = document.getElementById('validate_' + string),
				submitBtn = document.getElementById("submit_button");

			if ($(request).find('valid').text() == "false")
			{
				$elem.addClass("invalid_field").removeClass("valid_field");
				validate.innerHTML = "{{language->invalid}}";
				submitBtn.disabled = true;
			}
			else
			{
				$elem.addClass("valid_field").removeClass("invalid_field");
				validate.innerHTML = "{{language->validated}}";
				submitBtn.disabled = false;
			}
		});
	});

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