
{{* Link for deleting contact groups *}}
<a href="group/drop/{{$id}}?t={{$form_security_token}}" onclick="return confirmDelete();" id="group-delete-icon-{{$id}}" class="btn btn-clear" title="{{$delete}}" data-toggle="tooltip">
	<i class="fas fa-trash-alt" aria-hidden="true"></i>
</a>
