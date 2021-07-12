var LazyMePHP = LazyMePHP || {};

var ValidationsMethods = {
    STRING : 0,
    LATIN : 1,
    FLOAT : 2,
    INT : 3,
    NOTNULL : 4,
    LENGTH: 5,
    DATE: 6,
    POSTAL: 7,
    EMAIL: 8,
    LATINPUNCTUATION: 9
}

LazyMePHP.Init = function()
{

	// IE FIX
    Object.keys = Object.keys || function(
	    o, // object
	    k, // key
	    r  // result array
	) {
	    // initialize object and result
	    r = [];
	    // iterate over object keys
	    for (k in o)
	        // fill result array with non-prototypical keys
	        r.hasOwnProperty.call(o, k) && r.push(k);
	    // return result
	    return r
	};
	// END

	if (typeof(Init) == "function") Init();
}

LazyMePHP.ShowError = function(msg)
{
  /**
    * Change this function to treat messages differently
    */
    alert(msg);
}
LazyMePHP.ShowSuccess = function(msg)
{
  /**
    * Change this function to treat messages differently
    */
    alert(msg);
}

LazyMePHP.ValidateForm = function(form)
{
	var failmsg = form.getAttribute("validation-fail") || "";
	var error = false;
  var elements = form.querySelectorAll('input,select,textarea');
  for (var i=0;i<elements.length;i++)
  {
		var validationMethods = new Array();
		var v = elements[i].getAttribute("validation");
		if (v)
		{
      var _v = v.split(',');
      for (var j=0;j<_v.length;j++)
      {
				validationMethods[validationMethods.length] = _v[j];
      }
			if (!LazyMePHP.ValidateField(elements[i],validationMethods))
			{
				error = true;
				if (elements[i].getAttribute("validation-fail"))
					failmsg += (failmsg.length>0?"\n":"")+elements[i].getAttribute("validation-fail");
			}
    }
  }
	if (error && failmsg) LazyMePHP.ShowError(failmsg);

	return !error;
}

LazyMePHP.ValidateField = function(field, functions)
{
	var error = false;
	var arguments = functions;
	for (var i=0;i<arguments.length;i++)
	{
		switch(eval("ValidationsMethods."+arguments[i]))
		{
			case ValidationsMethods.STRING:
				if (!ValidateString(field.value)) error = true;
			break;
			case ValidationsMethods.LATIN:
				if (!ValidateLatinString(field.value)) error = true;
			break;
			case ValidationsMethods.FLOAT:
				if (!ValidateFloat(field.value)) error = true;
			break;
			case ValidationsMethods.INT:
				if (!ValidateInteger(field.value)) error = true;
			break;
			case ValidationsMethods.NOTNULL:
				if (!ValidateNotNull(field.value)) error = true;
			break;
			case ValidationsMethods.LENGTH:
				if (!ValidateLength(field.value,parseInt(arguments[++i]))) error = true;
			break;
			case ValidationsMethods.DATE:
				if (!ValidateDate(field.value)) error = true;
			break;
			case ValidationsMethods.POSTAL:
				if (!ValidatePostal(field.value)) error = true;
			break;
			case ValidationsMethods.EMAIL:
				if (!ValidateEmail(field.value)) error = true;
			break;
			case ValidationsMethods.LATINPUNCTUATION:
				if (!ValidateLatinStringWithPunctuation(field.value)) error = true;
			break;
			case ValidationsMethods.REGEXP:
				if (!ValidateRegExp(field.value, arguments[++i])) error = true;
			break;
		}
	}

	if (error) {
		/* Change here to set some color for the field when is invalid */
	}
	else {
		/* Change here to set some color for the field when is valid */
	}

	return !error;
}

function ValidateNotNull(value)
{
	return value.length!==0;
}

function ValidateFloat(value)
{
	if (value.length===0) return true;

	var str = value;
	reg = /^[+-]?(?=.)(?:\d+,)*\d*(?:\.\d+)?$/;
	return reg.test(str);
}
function ValidateInteger(value)
{
	if (value.length===0) return true;
		
	var str = value;
	reg = /^\d+$/;
	return reg.test(str);
}
function ValidateString(value)
{
	if (value.length===0) return true;
	
	var str = value;
	reg = /^[a-zA-Z0-9](?:([a-zA-Z0-9\x20]+$)?$)/;
	return reg.test(str);
}
function ValidateLatinString(value)
{
	if (value.length===0) return true; return true;

	var str = value;
	reg = /^[A-zÀ-ú0-9 ]+$/;
	return reg.test(str);
}
function ValidateLatinStringWithPunctuation(value)
{
	if (value.length===0) return true; return true;
	
	var str = value;
	reg = /^[A-zÀ-ú0-9 .:,;?!~+-€@#%&\/\\_\-\*\n]+$/;
	return reg.test(str);
}
function ValidateLength(value,size)
{
	if (value.length===0) return true;

	var str = value;
	return str.length===size;
}
function ValidateDate(value)
{
	if (value.length===0) return true;

	var str = value;
	reg = /^[0-9][0-9]\/[0-9][0-9]\/[0-9][0-9][0-9][0-9]+$/;
	return reg.test(str);
}
function ValidatePostal(value)
{
	if (value.length===0) return true;

	var str = value;
	reg = /^[0-9][0-9][0-9][0-9]-[0-9][0-9][0-9]$/;
	return reg.test(str);
}
function ValidateEmail(value)
{
	if (value.length===0) return true;

	var str = value;
	reg = /^([a-zA-Z0-9]+[\.|_|\-|£|$|%|&]{0,1})*[a-zA-Z0-9]{1}@([a-zA-Z0-9]+[\.|_|\-|£|$|%|&]{0,1})*([\.]{1}([a-zA-Z]{2,4}))$/;
	return reg.test(str);
}
function ValidateRegExp(value, regexp)
{
	if (value.length===0) return true;
	
	var str = value;
	var reg = new RegExp(eval(regexp));
	return reg.test(str);
}
Date.now = Date.now || function() { return +new Date; };
