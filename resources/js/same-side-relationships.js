function buildHtml(elements, display)
{
	for (var property in elements)
	{
		var style = '';
		if (display == false)
		{
			style = 'display: none;';
		}
		if (elements.hasOwnProperty(property))
		{
			var obj = elements[property];
			$('<tr/>', 
			{ 
				id:    currentNamespace+'-'+obj.fieldHandle+obj.sectionHandle,
				class: 'record-'+currentIncrement,
				style: style
			})
			    .append($('<td/>', 
		    	{ 
		    		text: obj.fieldName 
		    	}).append($('<input/>',
			    	{
			    		type:  'hidden',
			    		name:  currentNamespace+'[selectedFieldsPlusSection]['+obj.fieldHandle+obj.sectionHandle+'][fieldHandle]',
			    		value: obj.fieldHandle
			    	})).append($('<input/>',
			    	{
			    		type:  'hidden',
			    		name:  currentNamespace+'[selectedFieldsPlusSection]['+obj.fieldHandle+obj.sectionHandle+'][fieldName]',
			    		value: obj.fieldName
			    	}))
			    )
			    .append($('<td/>', 
		    	{ 
		    		text: obj.sectionName 
		    	}).append($('<input/>',
			    	{
			    		type:  'hidden',
			    		name:  currentNamespace+'[selectedFieldsPlusSection]['+obj.fieldHandle+obj.sectionHandle+'][sectionHandle]',
			    		value: obj.sectionHandle
			    	})).append($('<input/>',
			    	{
			    		type:  'hidden',
			    		name:  currentNamespace+'[selectedFieldsPlusSection]['+obj.fieldHandle+obj.sectionHandle+'][sectionName]',
			    		value: obj.sectionName
			    	}))
			    )
			    .append($('<td/>',{})
			    	.append($('<a/>',
				    {
				    	title:            'Remove',
				    	'data-increment': currentIncrement,
				    	class:            'ssr-delete delete icon'
				    }
			    	))
				)
			.appendTo($('#' + currentNamespace + '-ssr-table'));
			if (display == false) {
				$('tr.record-' + currentIncrement).fadeIn('fast');
			}
			currentIncrement++;
		}
	}
}

$( document ).ready(function() {
	
	// Build Saved Options
	buildHtml(currentSelections, true);

	// Handle new options
	$('#' + currentNamespace + '-ssr-table').on('click', '#' + currentNamespace + '-add-relationship', function() { 
		var fieldHandle   = $('#' + currentNamespace + '-linkedField').val();
		var fieldName     = $('#' + currentNamespace + '-linkedField option[value=\''+fieldHandle+'\']').text()
		
		var sectionHandle = $('#' + currentNamespace + '-linkedSection').val();
		var sectionName   = $('#' + currentNamespace + '-linkedSection option[value=\''+sectionHandle+'\']').text();

		// Make sure this combingation doesn't already exist.
		var exists = $('tr#'+currentNamespace+'-'+fieldHandle+sectionHandle);
		if (exists.length > 0) {
			alert('This combination of field and section already exists. Please add a unique combination');
			return false;
		}

		// Build the Object and pass it to the build function
		var key = fieldHandle+sectionHandle
		var obj = { key: {
			fieldHandle:   fieldHandle,
			fieldName:     fieldName,
			sectionHandle: sectionHandle,
			sectionName:   sectionName
		} };
		buildHtml(obj, false);
		
		return false;
		
	});

	// Delete the record
	$('#' + currentNamespace + '-ssr-table').on('click', '.ssr-delete', function() {
		var which = $(this).attr('data-increment');
		$('tr.record-'+which).fadeOut('fast', function() {
			$('tr.record-'+which).remove();
		});
		return false;
	});
});
