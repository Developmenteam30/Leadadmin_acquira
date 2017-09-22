<?php
	global $type;
?>
<div class="modal fade" id="genericledger" tabindex="-1" role="dialog" aria-labelledby="genericledger_title">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					<h4 class="modal-title" id="genericledger_title">Add a new entry</h4>
			</div>
			<div class="form-inline form-input" style="padding:15px 15px 0 15px;">
				<label for="ledgerType">Ledger Type</label>
				<select id="ledgerType" name="ledgerType" class="form-control">
					<option></option>
					<option>Offline</option>
					<option>Publisher</option>
					<option>Advertiser</option>
					<option>Leads</option>
				</select>
			</div>
			<div class="modal-body">
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-save-genericledger" type="button" class="btn btn-primary">Add Ledger Entry</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="newledger" tabindex="-1" role="dialog" aria-labelledby="newledger_title">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					<h4 class="modal-title" id="newledger_title"><?php echo ( 1 == $type ) ? 'Add a new client invoice' : 'Add a new payment'; ?></h4>
			</div>
			<div class="modal-body">
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-save-newledger" type="button" class="btn btn-primary">Add Ledger Entry</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="editledger" tabindex="-1" role="dialog" aria-labelledby="editledger_title">
  <div class="modal-dialog" role="document">
	<div class="modal-content">
	  <div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
		<h4 class="modal-title" id="editledger_title">Edit a ledger entry</h4>
	  </div>
	  <div class="modal-body">
	  </div>
	  <div class="modal-footer">
		<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
		<button id="modal-save-editledger" type="button" class="btn btn-primary">Save changes</button>
	  </div>
	</div>
  </div>
</div>

<div class="modal fade" id="deleteledger" tabindex="-1" role="dialog" aria-labelledby="deleteledger_title">
  <div class="modal-dialog" role="document">
	<div class="modal-content">
	  <div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
		<h4 class="modal-title" id="deleteledger_title">Delete a ledger entry</h4>
	  </div>
	  <div class="modal-body">
	  </div>
	  <div class="modal-footer">
		<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
		<button id="modal-deleteledger" type="button" class="btn btn-primary">Delete</button>
	  </div>
	</div>
  </div>
</div>

<div class="modal fade" id="newofflineledger" tabindex="-1" role="dialog" aria-labelledby="newofflineledger_title">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					<h4 class="modal-title" id="newofflineledger_title"><?php echo ( 1 == $type ) ? 'Add a new client invoice' : 'Add a new payment'; ?></h4>
			</div>
			<div class="modal-body">
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-save-newofflineledger" type="button" class="btn btn-primary">Add entry</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="deleteofflineledger" tabindex="-1" role="dialog" aria-labelledby="deleteofflineledger_title">
  <div class="modal-dialog" role="document">
	<div class="modal-content">
	  <div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
		<h4 class="modal-title" id="deleteofflineledger_title">Delete a ledger entry</h4>
	  </div>
	  <div class="modal-body">
	  </div>
	  <div class="modal-footer">
		<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
		<button id="modal-deleteofflineledger" type="button" class="btn btn-primary">Delete</button>
	  </div>
	</div>
  </div>
</div>

<div class="modal fade" id="editofflineledger" tabindex="-1" role="dialog" aria-labelledby="editofflineledger_title">
  <div class="modal-dialog" role="document">
	<div class="modal-content">
	  <div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
		<h4 class="modal-title" id="editofflineledger_title">Edit a ledger entry</h4>
	  </div>
	  <div class="modal-body">
	  </div>
	  <div class="modal-footer">
		<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
		<button id="modal-save-editofflineledger" type="button" class="btn btn-primary">Save changes</button>
	  </div>
	</div>
  </div>
</div>

<div class="modal fade" id="newphoneledger" tabindex="-1" role="dialog" aria-labelledby="newphoneledger_title">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					<h4 class="modal-title" id="newphoneledger_title">New Phone Ledger</h4>
			</div>
			<div class="modal-body">
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-save-newphoneledger" type="button" class="btn btn-primary">Add entry</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="deletephoneledger" tabindex="-1" role="dialog" aria-labelledby="deletephoneledger_title">
  <div class="modal-dialog" role="document">
	<div class="modal-content">
	  <div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
		<h4 class="modal-title" id="deletephoneledger_title">Delete a ledger entry</h4>
	  </div>
	  <div class="modal-body">
	  </div>
	  <div class="modal-footer">
		<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
		<button id="modal-deletephoneledger" type="button" class="btn btn-primary">Delete</button>
	  </div>
	</div>
  </div>
</div>

<div class="modal fade" id="editphoneledger" tabindex="-1" role="dialog" aria-labelledby="editphoneledger_title">
  <div class="modal-dialog" role="document">
	<div class="modal-content">
	  <div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
		<h4 class="modal-title" id="editphoneledger_title">Edit a phone ledger entry</h4>
	  </div>
	  <div class="modal-body">
	  </div>
	  <div class="modal-footer">
		<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
		<button id="modal-save-editphoneledger" type="button" class="btn btn-primary">Save changes</button>
	  </div>
	</div>
  </div>
</div>

<script type="text/javascript">
$('#modal-save-newledger').click( function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "ledger.php",
		type: "POST",
		async: true,
		data: $("#new_ledger").serialize()
	}).done(function(result){
		if(result.status == 1){
			window.location.reload(true);
		} else {
			alert(result.error);
		}
	});
});

$('#newledger').on('show.bs.modal', function(e) {
	var modal = $(this);

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'ledger.php',
		data: {
			'd': 'newLedger',
			'type': '<?php echo $type; ?>'
		},
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
});

$('#modal-deleteledger').click( function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "ledger.php",
		type: "POST",
		async: true,
		data: $("#delete_ledger").serialize()
	}).done(function(result){
		if(result.status == 1){
			window.location.reload(true);
		} else {
			alert(result.error);
		}
	});
});

$('#deleteledger').on('show.bs.modal', function(e) {
	var modal = $(this);
	var ledgerId = $(e.relatedTarget).data('ledger-id');

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'ledger.php',
		data: {
			'd': 'deleteLedger',
			'ledgerId': ledgerId
		},
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
});

$('#modal-save-editledger').click(function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "ledger.php",
		type: "POST",
		async: true,
		data: $("#edit_ledger").serialize()
	}).done(function(result){
		if(result.status == 1){
			window.location.reload(true);
		} else {
			alert(result.error);
		}
	});
});

$('#editledger').on('show.bs.modal', function(e) {
	var modal = $(this);
	var ledgerId = $(e.relatedTarget).data('ledger-id');

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'ledger.php',
		data: {
			'd': 'editLedger',
			'ledgerId': ledgerId
		},
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
});

$('#genericledger, #newledger, #editledger').on('hide.bs.modal', function(e) {
	$(this).find('.modal-body').html('');
});

$('#modal-save-newofflineledger').click( function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "offline.php",
		type: "POST",
		async: true,
		data: $("#new_offlineledger").serialize()
	}).done(function(result){
		if(result.status == 1){
			window.location.reload(true);
		} else {
			alert(result.error);
		}
	});
});

$('#newofflineledger').on('show.bs.modal', function(e) {
	var modal = $(this);

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'offline.php',
		data: {
			'd': 'newOfflineLedger',
			'type': '<?php echo $type; ?>'
		},
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
});

$('#modal-deleteofflineledger').click( function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "offline.php",
		type: "POST",
		async: true,
		data: $("#delete_offlineledger").serialize()
	}).done(function(result){
		if(result.status == 1){
			window.location.reload(true);
		} else {
			alert(result.error);
		}
	});
});

$('#deleteofflineledger').on('show.bs.modal', function(e) {
	var modal = $(this);
	var ledgerId = $(e.relatedTarget).data('ledger-id');

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'offline.php',
		data: {
			'd': 'deleteOfflineLedger',
			'ledgerId': ledgerId
		},
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
});

$('#modal-save-editofflineledger').click(function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "offline.php",
		type: "POST",
		async: true,
		data: $("#edit_offlineledger").serialize()
	}).done(function(result){
		if(result.status == 1){
			window.location.reload(true);
		} else {
			alert(result.error);
		}
	});
});

$('#editofflineledger').on('show.bs.modal', function(e) {
	var modal = $(this);
	var ledgerId = $(e.relatedTarget).data('ledger-id');

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'offline.php',
		data: {
			'd': 'editOfflineLedger',
			'ledgerId': ledgerId
		},
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
});

$('#newofflineledger, #editofflineledger').on('hide.bs.modal', function(e) {
	$(this).find('.modal-body').html('');
});

$('#modal-deletephoneledger').click( function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "leads.php",
		type: "POST",
		async: true,
		data: $("#delete_phoneledger").serialize()
	}).done(function(result){
		if(result.status == 1){
			window.location.reload(true);
		} else {
			alert(result.error);
		}
	});
});

$('#deletephoneledger').on('show.bs.modal', function(e) {
	var modal = $(this);
	var ledgerId = $(e.relatedTarget).data('ledger-id');

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'leads.php',
		data: {
			'd': 'deletePhoneLedger',
			'ledgerId': ledgerId
		},
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
});

$('#modal-save-editphoneledger').click(function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "leads.php",
		type: "POST",
		async: true,
		data: $("#edit_phoneledger").serialize()
	}).done(function(result){
		if(result.status == 1){
			window.location.reload(true);
		} else {
			alert(result.error);
		}
	});
});

$('#editphoneledger').on('show.bs.modal', function(e) {
	var modal = $(this);
	var ledgerId = $(e.relatedTarget).data('ledger-id');

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'leads.php',
		data: {
			'd': 'editPhoneLedger',
			'ledgerId': ledgerId
		},
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
});

$('#newphoneledger, #editphoneledger').on('hide.bs.modal', function(e) {
	$(this).find('.modal-body').html('');
});

$('#genericledger').on('shown.bs.modal', function(e) {
	$("#genericledger select[name='ledgerType']").select2({
		placeholder: "Select a ledger type",
		allowClear: true
	});
});

$("#genericledger select[name='ledgerType']").on('change', function(e) {
	var val = $(this).val();
	var modal = $(this);

	if( 'Publisher' == val ) {
		$.ajax({
			cache: false,
			type: 'POST',
			url: 'ledger.php',
			data: {
				'd': 'newLedger',
				'type': 0
			},
			success: function(data) {
				modal.parent().siblings('.modal-body').html(data);
			}
		});
	} else if( 'Advertiser' == val ) {
		$.ajax({
			cache: false,
			type: 'POST',
			url: 'ledger.php',
			data: {
				'd': 'newLedger',
				'type': 1
			},
			success: function(data) {
				modal.parent().siblings('.modal-body').html(data);
			}
		});
	} else if( 'Offline' == val ) {
		$.ajax({
			cache: false,
			type: 'POST',
			url: 'offline.php',
			data: {
				'd': 'newOfflineLedger',
				'type': 0
			},
			success: function(data) {
				modal.parent().siblings('.modal-body').html(data);
			}
		});
	} else if( 'Leads' == val ) {
		$.ajax({
			cache: false,
			type: 'POST',
			url: 'leads.php',
			data: {
				'd': 'newPhoneLedger'
			},
			success: function(data) {
				modal.parent().siblings('.modal-body').html(data);
			}
		});
	}
});

$('#modal-save-genericledger').click( function(event) {
	event.preventDefault();
	var val = $('#ledgerType').val();

	if( 'Publisher' == val ) {
		var response = $.ajax({
			url: "ledger.php",
			type: "POST",
			async: true,
			data: $("#new_ledger").serialize()
		}).done(function(result){
			if(result.status == 1){
				window.location = '/leadadmin/ledger.php?type=0';
			} else {
				alert(result.error);
			}
		});
	} else if( 'Advertiser' == val ) {
		var response = $.ajax({
			url: "ledger.php",
			type: "POST",
			async: true,
			data: $("#new_ledger").serialize()
		}).done(function(result){
			if(result.status == 1){
				window.location = '/leadadmin/ledger.php?type=1';
			} else {
				alert(result.error);
			}
		});
	} else if( 'Offline' == val ) {
		var response = $.ajax({
			url: "offline.php",
			type: "POST",
			async: true,
			data: $("#new_offlineledger").serialize()
		}).done(function(result){
			if(result.status == 1){
				window.location = '/leadadmin/offline.php';
			} else {
				alert(result.error);
			}
		});
	} else if( 'Leads' == val ) {
		var response = $.ajax({
			url: "leads.php",
			type: "POST",
			async: true,
			data: $("#new_phoneledger").serialize()
		}).done(function(result){
			if(result.status == 1){
				window.location = '/leadadmin/leads.php';
			} else {
				alert(result.error);
			}
		});
	}
});


</script>
