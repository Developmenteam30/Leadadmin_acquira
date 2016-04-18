$.ajaxSetup({ cache: false });

function display(contentLabel, options, pauseAutoRefresh, displayAfter){ 
	if(pauseAutoRefresh && pauseAutoRefresh == true){
		automaticRefresh = false;
	}
	var filename = window.location.href.slice(window.location.href.lastIndexOf("/")+1);
	var response = $.ajax({
		url: filename,
		type: "POST",
		async: true,
		data: ({
			"d" : contentLabel
			, "options": options
		})
	}).done(function(responseText){ 
		if(options && options.sub){
			$('#'+contentLabel+'_'+options.sub).html(responseText);
		} else { 
			$('#'+contentLabel).html(responseText);
		}
		if(displayAfter){
			if(options && options.callbackParams){
				displayAfter(options.callbackParams);
			} else { 
				displayAfter();
			}
		}
	});	
	if(options && options.sub){
		$('#'+contentLabel+'_'+options.sub).html('Loading...').show();
	} else { 
		$('#'+contentLabel).html('Loading...').show();
	}
}

function toggleHidden(contentLabel, options){
	if(options && options.sub){
		content = $('#'+contentLabel+'_'+options.sub);
		link = $('#link_'+contentLabel+'_'+options.sub);
	} else { 
		content = $('#'+contentLabel);
		link = $('#'+contentLabel);
	}
	content.toggle();
	if(content.css('display') == 'none'){ 
		link.html(options.hiddenText);
	} else { 
		link.html(options.shownText);
	}
}

function element(parentContainer, elementName, options){ 
	var filename = window.location.href.slice(window.location.href.lastIndexOf("/")+1);
	var response = $.ajax({
		url: filename,
		type: "POST",
		async: true,
		data: ({
			"d" : elementName
			, "options": options
		})
	}).done(function(responseText){ 
		$('#'+parentContainer).append(responseText);
	});
}

function closeContent(contentLabel, options){ 
	automaticRefresh = true;
	if(options && options.sub){
		$('#'+contentLabel+'_'+options.sub).hide();
	} else { 
		$('#'+contentLabel).hide();
	}
}

$(document).ready(function(){ 
	$("body").on('click', 'a.nonLink', function(event){ event.preventDefault(); });
	$("body").on("click", "input.dateSelector", function(){
        if (!$(this).hasClass("hasDatepicker"))
        {
            $(this).datepicker({ dateFormat: "yy-mm-dd" });
            $(this).datepicker("show");
        }
    });
	display('errorCount');
});