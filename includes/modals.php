<div class="modal fade" id="newledger" tabindex="-1" role="dialog" aria-labelledby="newledger_title">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					<h4 class="modal-title" id="newcompany_title"><?php echo ( 1 == $type ) ? 'Add a new client invoice' : 'Add a new payment'; ?></h4>
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
					<h4 class="modal-title" id="newcompany_title"><?php echo ( 1 == $type ) ? 'Add a new client invoice' : 'Add a new payment'; ?></h4>
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

$('#newledger, #editledger').on('hide.bs.modal', function(e) {
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
</script>
