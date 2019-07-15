<?php

$filter=(string)$this->input->get("filter",true);

$status_codes=array(
    'all' =>'View all',
    'draft'=>'Draft',
    'submitted'=>'Submitted',
    'processed'=>'Processed',
    'accepted'=>'Accepted',
    'closed'=>'Closed'
);

$task_codes=array(
    '0'=>'Work in progress',
    '1'=>'Completed'
);

if (!array_key_exists($filter,$status_codes))
{
    $filter='all';
}

?>

<style>
.label{ text-transform:uppercase;font-weight:normal;padding:5px;}
.label-draft{background-color:#9E9E9E;display:block;}
.label-submitted{background-color:#3a87ad}
.label-closed{background-color:#0099FF;display:block;}
.label-processed{background-color:orange}
.label-accepted{background-color:#00CC00;display:block;}

.label-0{background-color:orange}
.label-1{background-color:#00CC00}

.grid-table td{vertical-align: top;}
.grid-table .shortname{font-size:smaller;color:gray;}

.task-team-container .person{border-bottom:1px solid #dcdcdc; padding:5px;}
.task-team-container .person .input-radio{display:none;}
.task-team-container .person {position: relative;}
.task-team-container .person .btn-assign {position: absolute; right:10px; top:15px;}
.task-team-container .person:hover{background:#dcdcdc;}
</style>

<h1 class="page-title">Data Deposit Projects</h1>


<?php $message=$this->session->flashdata('message');?>
<?php echo ($message!="") ? '<div class="success">'.$message.'</div>' : '';?>

<?php $error=$this->session->flashdata('error');?>
<?php echo ($error!="") ? '<div class="error">'.$error.'</div>' : '';?>


<form class="left-pad" style="margin-bottom:30px;" method="GET" id="user-search" >
    <input type="text" size="40" name="keywords" id="keywords" value="<?php echo form_prep($this->input->get("keywords",true));?>">
    <input type="hidden" name="filter" value="<?php echo form_prep($filter);?>"/>
    <input type="submit" value="Search" name="search">
    <?php if ($this->input->get("keywords")):?>
        <a href="<?php echo site_url('admin/datadeposit');?>">Reset</a>
    <?php endif;?>
</form>


<ul class="nav nav-tabs">
<?php foreach($status_codes as $code=>$status):?>
	<li <?php echo ($code==$filter) ? 'class="active"' : '';?>><a href="<?php echo site_url('admin/datadeposit?filter='.$code);?>" ><?php echo $status;?></a></li>
<?php endforeach;?>
</ul>

<?php		
		$sort_by=$this->input->get("sort_by");
		$sort_order=$this->input->get("sort_order");			
?>

<?php if (count($projects)==0):?>
	No projects were found.
    <?php return;?>
<?php endif;?>

<div style="font-weight:bold;">
Total projects found: <span><?php echo count($projects);?></span>
</div>

<table class="grid-table table table-striped" width="100%" cellspacing="0" cellpadding="0">
  <thead class="header">
  	<th> <?php echo create_sort_link($sort_by,$sort_order,'status',t('Status'),current_url(),array('filter')); ?>  </th>
    <th> <?php echo create_sort_link($sort_by,$sort_order,'title',t('title'),current_url(),array('filter')); ?> </th>
    <!--<th> <?php echo create_sort_link($sort_by,$sort_order,'shortname',t('Short name'),current_url(),array('filter')); ?>  </th>-->
    <th> <?php echo create_sort_link($sort_by,$sort_order,'last_modified',t('Changed'),current_url(),array('filter')); ?>  </th>
    <th nowrap="nowrap"> <?php echo create_sort_link($sort_by,$sort_order,'created_on',t('Created'),current_url(),array('filter')); ?>  </th>
    <th nowrap="nowrap"> <?php echo create_sort_link($sort_by,$sort_order,'created_by',t('Creator'),current_url(),array('filter')); ?>  </th>
    <th></th>
    <th nowrap="nowrap"></th>
    </thead>
  <tbody>
    <?php foreach($projects as $project): ?>
    <tr>
    	<td><span class="label label-<?php echo $project->status;?>"><?php echo $project->status;?></span></td>
        <td>
            <div><a href="<?php echo site_url('admin/datadeposit/id/'.$project->id);?>"><?php echo $project->title;?></a></div>
            <div class="shortname">
                <?php echo $project->shortname;?>
            </div>
        </td>
        <td nowrap="nowrap"><?php echo date("m-d-Y",$project->last_modified);?></td>
        <td nowrap="nowrap"><?php echo date("m-d-Y",$project->created_on);?></td>
        <td><?php echo $project->created_by;?></td>
        <td><?php if(isset($project->task_user)):?>
                <a href="<?php echo site_url('admin/datadeposit/tasks/info/'.$project->task_id);?>">
                    <span class="label label-<?php echo $project->task_status;?>" title="<?php echo @$task_codes[$project->task_status]. ' - '. $project->task_user;?> ">
                        <?php
                            $user=$project->task_user;
                            $name_parts=explode(" ",$user);
                            foreach($name_parts as $part)
                            {
                                echo strtoupper(substr($part,0,1));
                            }
                        ?>
                    </span>
                </a>
            <?php endif;?>
        </td>
        <td nowrap="nowrap">
            <a class="assign" href="<?php echo site_url('admin/datadeposit/assign/'.$project->id);?>" data-id="<?php echo $project->id;?>">Assign</a> |
            <a href="<?php echo site_url('admin/datadeposit/id/'.$project->id);?>">Edit</a> |
            <a href="<?php echo site_url('admin/datadeposit/delete/'.$project->id);?>">Delete</a>
            </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php /*

<button type="button" class="btn btn-primary" data-toggle="modal" data-target=".task-assign-modal">Small modal</button>


<div class="modal fade task-assign-modal " tabindex="-1" role="dialog" id="assigntask-modal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Assign task</h4>
            </div>
            <div class="modal-body" xxstyle="height:200px;overflow: auto;">

                <div class="task-team-container">
                    <?php foreach($tasks_team as $user):$user=(object)$user;?>
                        <div class="person">
                        <label for="user_<?php echo $user->id;?>">
                            <input class="input-radio" id="user_<?php echo $user->id;?>" name="user_id" type="radio" value="<?php echo $user->id;?>">
                            <h4><?php echo $user->first_name;?> <?php echo $user->last_name;?></h4>
                            <span class="email"><?php echo $user->email;?></span>
                        </label>
                            <button type="button" class="btn btn-default btn-assign" data-dismiss="modal">Assign</button>
                        </div>
                <?php endforeach;?>
            </div>


            </div>
            <!--<div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary">Save changes</button>
            </div>-->
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->
*/?>

<!--
<script type="text/javascript">

    $(".assign").click(function(e) {
        window.assign_button_clicked=$(this);
        $('#assigntask-modal').modal('show');
        return false;
    });

    /*$('#assigntask-modal').on('hide.bs.modal', function (event) {
        var button = $(event.relatedTarget) // Button that triggered the modal
        window.button_=button;

        console.log(event.relatedTarget);

        console.log(button);
        return;
        var recipient = button.data('whatever') // Extract info from data-* attributes
        // If necessary, you could initiate an AJAX request here (and then do the updating in a callback).
        // Update the modal's content. We'll use jQuery here, but you could use a data binding library or other methods instead.
        var modal = $(this)
        modal.find('.modal-title').text('New message to ' + recipient)
        modal.find('.modal-body input').val(recipient)
    })*/

    $(".btn-assign").click(function(e){
        console.log("assigned project to"+ window.assign_button_clicked.data("id"));

    });

</script>
-->