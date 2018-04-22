var captions = {captiontable};
(function ($) {
	$(function() {
		$.fn.addTableRow = function(row){
		    // We can use this later to see if the row has been added to the table yet
		    var added = false;

		    // Get the ID of the table
		    var tid = $(this).attr('id');

		    // See if the table is set as a tableDrag table
		    if(typeof Drupal.tableDrag[tid] != 'undefined'){

		        // initialize order variable
		        var order = false;

		        // Set some variables for easier access
		        var tableDrag = Drupal.tableDrag[tid];
		        var settings = tableDrag.tableSettings;

		        // Loop through the classes in the settings for the table 
		        for(var aClass in settings){

		            // Loop through the settings for the class
		            for(var i in settings[aClass]){

		                // check which action the class uses, we could add the other actions
		                // here if we are worried about heirarchy or matching
		                switch(settings[aClass][i].action){
		                    case 'order':

		                        // if this class is used for ordering, assign it to order
		                        order = aClass;
		                        break;
		                }
		            }
		        }


		        if(order){

		            // find the max weight currently avaiable in the table
		            var max = $(this).find('td.'+order).sort(function (a, b) {
		                return +a.value - +b.value;
		            }).last().val();

		            // Make sure the row has the draggable class
		            row.addClass('draggable');

		            // Add the row to the table, we don't do this earlier so that if
		            // there is a value set in the weight field it is not included in the max
		            // This could be done earlier, and setting the value could be ommitted
		            // if you wanted to add the row to a specific locaiton in the table
		            added = $(this).append(row);

		            // Set the value of the order field to max+1
		            $('.'+order, row).val(max+1);

		            // Add the row so that tableDrag knows about it and can run it's
		            // own hooks accordingly
		            tableDrag.rowObject = new tableDrag.row(row);
		            tableDrag.makeDraggable(row);
		        }
		    }

		    // If the row wasn't added, order handling isn't used, just add it to the table.
		    if(!added) $(this).append(row);
		}
		// $(".form-item-aarticles .form-item").each(function() {
		// 	var el_input = $(this).find("input").attr("id");
		// 	var id = el_input.replace(/\D/g,'');
		// 	$(this).data('caption')
		// }

		function add_to_table(id, caption=null, title=null) {
			$('#edit-extra-title').val("");
			var newRow_tpl = '{newrow}';
			newRow_tpl = newRow_tpl.replace(/{nid}/g, id);
			if (!caption || !title) {
				if (title && (id in captions)) {
					caption = captions[id]['teaser'];
					newRow_tpl = newRow_tpl.replace(/{title}/g, title);
				}
				if (caption && (id in captions)) {
					title = captions[id]['title'];
					newRow_tpl = newRow_tpl.replace(/{caption}/g, caption);
				}
				if (!caption || !title) {
					$.get('/newsletters-fetch-data/' + id, null, function(response) {
						newRow_tpl = newRow_tpl.replace(/{title}/g, response.title);
						newRow_tpl = newRow_tpl.replace(/{caption}/g, response.body);
						add_row(id, response.body, response.title, newRow_tpl);
					});
				} else {
					add_row(id, caption, title, newRow_tpl);
				}
			} else {
				newRow_tpl = newRow_tpl.replace(/{title}/g, title);
				newRow_tpl = newRow_tpl.replace(/{caption}/g, caption);
				add_row(id, caption, title, newRow_tpl);
			}

			function add_row(id, caption, title, tpl) {
				newRow = $(tpl);
				newRow.find('a.remove-row').click(function() {
				  parent = $(this).closest('tr');
				  chkbox_tpl = '{newchkbox}';
				  chkbox_tpl = chkbox_tpl.replace(/{nid}/g, id);
				  chkbox_tpl = chkbox_tpl.replace(/{title}/g, title);
				  parent.fadeOut(function() {
	  				  $('#edit-aarticles').append(chkbox_tpl);
				  });
				});
				if($("#tr-" + id).length == 0) {
					$('#slides-order').addTableRow(newRow);
					$(this).fadeOut(function() { });
				}
			}
		}

		$('#edit-extra-title').on('autocompleteSelect', function(event, node) {
			add_to_table($(this).val());
		});


		$(".form-item-aarticles .form-item").click(function() {
			// $(this).find('input').each(function() {
			// 	$(this).attr('checked', true);
			// });
			var title = $(this).text();
			var el_input = $(this).find("input").attr("id");
			var id = el_input.replace(/\D/g,'');
			add_to_table(id, null, title);
		});
	});
})(jQuery);