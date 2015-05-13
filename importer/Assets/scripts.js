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