<?php

if (!$user->rights->projet->lire) {
	accessforbidden();
	echo "access forbidden";
} 

$datenow = date('Y-m-d');

$sql = "SELECT p.*, SUM(ptt.task_duration)*ptt.thm/60/60 AS totalspent FROM ".MAIN_DB_PREFIX."projet p 
		LEFT JOIN ".MAIN_DB_PREFIX."projet_task pt ON pt.fk_projet = p.rowid 
		LEFT JOIN ".MAIN_DB_PREFIX."projet_task_time ptt ON ptt.fk_task = pt.rowid 
		WHERE p.fk_statut=1 	
		GROUP BY p.rowid,ptt.fk_user 
		ORDER BY p.budget_amount DESC";


$result = $db->query($sql);
$nbtotalofrecords = $db->num_rows($result);


$projects = array();
$timespent = 0;
$totalporder = 0;
$totalexpenses = 0;
$budget = 0;
$i=0;
while ($i<$nbtotalofrecords) {
	$obj = $db->fetch_object($result);	
	if (isset($projects[$obj->rowid])) {
		$projects[$obj->rowid]["spent"] += (int)$obj->totalspent;
		
	} else {
		$projects[$obj->rowid] = array ("ref"=>$obj->rowid,
										"title"=>$obj->title,
										"budget"=>(int)$obj->budget_amount,
										"spent"=>(int)$obj->totalspent);

		$budget += (int)$obj->budget_amount;
	}
	
	$timespent += (int)$obj->totalspent;	
	$i++;
}

$db->free($result);

//----start: adding PO to spent item
$porders = array();
$sql1 = "SELECT fk_projet, SUM(total_ttc) as total_po FROM ".MAIN_DB_PREFIX."commande_fournisseur 
			WHERE fk_user_approve>0 GROUP BY fk_projet";
$result1 = $db->query($sql1);
$nbtotal1 = $db->num_rows($result1);
$i=0;
while ($i<$nbtotal1) {
	$obj = $db->fetch_object($result1);	
	$porders[$obj->fk_projet] = (int)$obj->total_po;	
	$i++;
}

foreach ($projects as $pid=>$data) {
	if (isset($porders[$pid])) {
		$projects[$pid]["spent"] += (int)$porders[$pid];
		$totalporder += (int)$porders[$pid];
	}
}
//----end: adding PO to spent item


//----start: adding expenses to spent item
$expenses = array();
$sql2 = "SELECT ed.fk_projet, SUM(ed.total_ht) as total_exp FROM ".MAIN_DB_PREFIX."expensereport_det ed 
		 LEFT JOIN ".MAIN_DB_PREFIX."expensereport ex ON ed.fk_expensereport = ex.rowid 
			WHERE ex.fk_user_approve>0 GROUP BY ed.fk_projet";
$result2 = $db->query($sql2);
$nbtotal2 = $db->num_rows($result2);
$i=0;
while ($i<$nbtotal2) {
	$obj = $db->fetch_object($result2);	
	$expenses[$obj->fk_projet] = (int)$obj->total_exp;	
	$i++;
}

foreach ($projects as $pid=>$data) {
	if (isset($expenses[$pid])) {
		$projects[$pid]["spent"] += (int)$expenses[$pid];
		$totalexpenses += (int)$expenses[$pid];
	}
}
//----end: adding expenses to spent item


//processing data for view
$totalspent = $timespent+$totalporder+$totalexpenses;
$balance = $budget-$totalspent;

$blncolor = "green";
if ($balance<0) {
	$blncolor="red";
}

$labels = array();
$budgets = array();
$spents = array();

foreach ($projects as $data) {
	$labels[] = $data["title"];
	$budgets[] = $data["budget"];
	$spents[] = $data["spent"];
}

?>

<div style='clear:both; overflow:auto; margin-bottom: 30px;'>
<div class='dashboard_budget'>
	<figure>		
		<div class='figurein'>
			<div class="budgettitle">Budget </div>
			<div class="famount">
				$<?php echo number_format($budget);?>
			</div>
		</div>				
	</figure>

	<figure>
		<div class='figurein'>
			<div class="budgettitle">Spent </div>
			<div class="famount">
				$<?php echo number_format($totalspent);?>
			</div>
		</div>		
	</figure>
	
	<figure style="border-right: 0;">
		<div class='figurein'>
			<div class="budgettitle">Left to Spend </div>
			<div class="famount" style='color:<?php echo $blncolor; ?>'>
				$<?php echo number_format($balance);?>
			</div>
		</div>		
	</figure>
</div>
</div>

<div class="fichecenter">

<div class="fichehalfleft">
	<div class="budgettitle">Budget by Projects </div>
	<div class="budgetchart">
	<canvas id="canvas_idgraphstatus"></canvas>
	</div>


	<script id="idgraphstatus">

	window.chartColors = {
		green: 'rgb(105, 191, 100)',
		red: 'rgb(221, 51, 51)',
		blue: 'rgb(41, 128, 230)',
		orange: 'rgb(255, 159, 64)',
		yellow: 'rgb(255, 205, 86)',
		greeny: 'rgb(75, 192, 192)',
		pink: 'rgb(255, 99, 132)',
		cyan: 'rgb(54, 203, 235)',
		purple: 'rgb(162, 74, 236)',
		purple2: 'rgb(153, 102, 255)',
		grey: 'rgb(201, 203, 207)',
		white: 'rgb(250, 245, 245)'
	};

	var budget_config = {
			type: 'pie',
			data: {
				datasets: [{
					label: 'Budget by Projects',
					data: <?php echo json_encode(array_values($budgets)); ?>,
					backgroundColor: [window.chartColors.green,
										window.chartColors.red,
										window.chartColors.purple,
										window.chartColors.orange,
										window.chartColors.cyan,
										window.chartColors.pink,
										window.chartColors.blue,
										window.chartColors.yellow]
					}],
				labels: <?php echo json_encode(array_values($labels)); ?>				
				},
				
			options: {
				responsive: true,
				legend: {
					position: 'right',
				},
				title: {
					display: false,
					text: 'Budget by Projects'
				},
				animation: {
					animateScale: true,
					animateRotate: true
				},
			
				tooltips: {
				  callbacks: {
						label: function(tooltipItem, data) {
							var value = data.datasets[tooltipItem.datasetIndex].data[tooltipItem.index];
							value = value.toString();
							value = value.split(/(?=(?:...)*$)/);
							value = value.join(',');
							var label = data.labels[tooltipItem.index];
							return label+': $'+value;
						}
				  } 
				} //end tooltips
			
			}
		};




	var ctx = document.getElementById("canvas_idgraphstatus").getContext("2d");
	var chart = new Chart(ctx, budget_config);
	</script>
	


	<div class="budgettitle">Budget vs Spent by Project</div>
	<table class='budgettbl'>
		<tr>
			<th>Project</th>
			<th>Budget</th>
			<th>Spent</th>
			<th>Balance</th>
		</tr>
		
		<?php 
		foreach ($projects as $pid=>$data) { 
		$fbal = $data['budget']-$data['spent'];
		$fcolor = "green";
		if ($fbal<0) $fcolor="red";
		$url = DOL_URL_ROOT.'/projet/element.php?id='.$pid;
		?>
		
		<tr>
			<td><a href='<?php echo $url; ?>'><?php echo $data['title']; ?></a></td>
			<td align="right">$<?php echo number_format($data['budget']); ?></td>
			<td align="right">$<?php echo number_format($data['spent']); ?></td>
			<td align="right" style='color:<?php echo $fcolor; ?>'>$<?php echo number_format($fbal); ?></td>
		</tr>
		
		<?php } ?>

		<tr>
			<td><b>Total</b></td>
			<td align="right"><b>$<?php echo number_format($budget); ?></b></td>
			<td align="right"><b>$<?php echo number_format($totalspent); ?></b></td>
			<td align="right" style='color:<?php echo $blncolor; ?>'><b>$<?php echo number_format($balance); ?></b></td>
		</tr>
		
	</table>	
</div>

<div class="fichehalfright">
	<div class="budgettitle">Budget vs Spent </div>
	<div class="budgetchart">
	<canvas id="canvas_idgraphspent"></canvas>
	</div>

	<script id="idgraphspent">
	var spent_config = {
			type: 'pie',
			data: {
				datasets: [{
					label: 'Budget by Projects',
					data: [<?php echo "$timespent,$totalporder,$totalexpenses"; echo ($balance>0)?",$balance":""; ?>],
					backgroundColor: [window.chartColors.cyan,
										window.chartColors.pink,
										window.chartColors.yellow,
										window.chartColors.white,]
					}],
				labels: ['Time spent on tasks','Purchase orders','Staff Expenses'<?php echo ($balance>0)?",'Balance'":""; ?>]				
				},
				
			options: {
				responsive: true,
				legend: {
					position: 'right',
				},
				title: {
					display: false,
					text: 'Budget vs Spent'
				},
				animation: {
					animateScale: true,
					animateRotate: true
				},
			
				tooltips: {
				  callbacks: {
						label: function(tooltipItem, data) {
							var value = data.datasets[tooltipItem.datasetIndex].data[tooltipItem.index];
							value = value.toString();
							value = value.split(/(?=(?:...)*$)/);
							value = value.join(',');
							var label = data.labels[tooltipItem.index];
							return label+': $'+value;
						}
				  } 
				} //end tooltips
			
			}
		};




	var ctx = document.getElementById("canvas_idgraphspent").getContext("2d");
	var chart = new Chart(ctx, spent_config);
	</script>



</div>


</div>