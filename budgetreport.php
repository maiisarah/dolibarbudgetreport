<?php

if (!$user->rights->projet->lire) {
	accessforbidden();
	echo "access forbidden";
} 

$datenow = date('Y-m-d');
$projects = array();
$mobudget = array();
$mospent = array();
$cleanmos = array();

$totaltime = 0;
$totalvendinv = 0;
$totalexpenses = 0;
$budget = 0;

$sql = "SELECT p.* FROM ".MAIN_DB_PREFIX."projet p 
		WHERE p.fk_statut=1 AND p.budget_amount>0 	
		ORDER BY p.budget_amount DESC";


$result = $db->query($sql);
$nbtotalofrecords = $db->num_rows($result);

$i=0;
while ($i<$nbtotalofrecords) {
	$obj = $db->fetch_object($result);	

	$projects[$obj->rowid] = array ("ref"=>$obj->rowid,
									"title"=>$obj->title,
									"budget"=>(int)$obj->budget_amount,
									"spent"=>0);
	//total up all budget
	$budget += (int)$obj->budget_amount;

	//separate budget by months
	if (empty($obj->datee) || $obj->datee<$obj->dateo) {
		$yrmo = date('Y-m',strtotime($obj->dateo));
		$cleanmos[$yrmo] = $yrmo;
		
		$mobudget[$yrmo] += (int)$obj->budget_amount;
		
	} else if (!empty($obj->dateo) && $obj->budget_amount>0) {
		$j = 0; $molist = array();
		$yrmo = date('Y-m',strtotime($obj->dateo));
		$yrme = date('Y-m',strtotime($obj->datee));
		
		while ($yrmo<=$yrme && $j<37) {
			$molist[$j] = $yrmo;
			$cleanmos[$yrmo] = $yrmo;
			
			$j++;
			$yrmo = date("Y-m",strtotime($obj->dateo." +$j months") );
		}
		//echo $j; var_dump ($molist); exit;
		
		$permonth = (int)($obj->budget_amount/$j);
		foreach ($molist as $mos) {
			$mobudget[$mos] += $permonth;
		}
	}
	
	$i++;
}

$db->free($result);

//var_dump ($mobudget); exit;

//----start: adding timespent to spent item
$timespent = array();
$sql0 = "SELECT pt.fk_projet, ptt.task_date, SUM(ptt.task_duration)*ptt.thm/60/60 AS totalspent FROM ".MAIN_DB_PREFIX."projet_task pt 
			LEFT JOIN ".MAIN_DB_PREFIX."projet_task_time ptt ON ptt.fk_task = pt.rowid 
		 GROUP BY pt.fk_projet, ptt.fk_user, ptt.task_date";
$result0 = $db->query($sql0);
$nbtotal0 = $db->num_rows($result0);
$i=0;
while ($i<$nbtotal0) {
	$obj = $db->fetch_object($result0);	
	if ($obj->totalspent>0) {
		$timespent[$obj->fk_projet][$obj->task_date] += (int)$obj->totalspent;	
	}
	$i++;
}

foreach ($projects as $pid=>$data) {
	if (isset($timespent[$pid])) {
		foreach ($timespent[$pid] as $dt=>$val) {
			$projects[$pid]["spent"] += (int)$val;
			$totaltime += (int)$val;	
			
			$yrmo = date('Y-m',strtotime($dt));
			$cleanmos[$yrmo] = $yrmo;
			$mospent[$yrmo] += (int)$val;	
		}
	}
}
//----end: adding timespent to spent item


//----start: adding vendor invoices to spent item
$vendorinvs = array();
$sql1 = "SELECT datef, fk_projet, SUM(total_ttc) as total_inv FROM ".MAIN_DB_PREFIX."facture_fourn 
			WHERE fk_statut IN (1,2) GROUP BY fk_projet, datef";
$result1 = $db->query($sql1);
$nbtotal1 = $db->num_rows($result1);
$i=0;
while ($i<$nbtotal1) {
	$obj = $db->fetch_object($result1);	
	$vendorinvs[$obj->fk_projet][$obj->datef] += (int)$obj->total_inv;	
	$i++;
}

foreach ($projects as $pid=>$data) {
	if (isset($vendorinvs[$pid])) {
		foreach ($vendorinvs[$pid] as $dt=>$val) {
			$projects[$pid]["spent"] += (int)$val;
			$totalvendinv += (int)$val;
			
			$yrmo = date('Y-m',strtotime($dt));
			$cleanmos[$yrmo] = $yrmo;
			$mospent[$yrmo] += (int)$val;	
		}
	}
}
//----end: adding vendor invoices to spent item


//----start: adding expenses to spent item
$expenses = array();
$sql2 = "SELECT ed.date, ed.fk_projet, SUM(ed.total_ht) as total_exp FROM ".MAIN_DB_PREFIX."expensereport_det ed 
		 LEFT JOIN ".MAIN_DB_PREFIX."expensereport ex ON ed.fk_expensereport = ex.rowid 
			WHERE ex.fk_user_approve>0 GROUP BY ed.fk_projet, date ";
$result2 = $db->query($sql2);
$nbtotal2 = $db->num_rows($result2);
$i=0;
while ($i<$nbtotal2) {
	$obj = $db->fetch_object($result2);	
	$expenses[$obj->fk_projet][$obj->date] += (int)$obj->total_exp;	
	$i++;
}

foreach ($projects as $pid=>$data) {
	if (isset($expenses[$pid])) {
		foreach ($expenses[$pid] as $dt=>$val) {
			$projects[$pid]["spent"] += (int)$val;
			$totalexpenses += (int)$val;

			$yrmo = date('Y-m',strtotime($dt));
			$cleanmos[$yrmo] = $yrmo;
			$mospent[$yrmo] += (int)$val;	
		}
	}
}
//----end: adding expenses to spent item


//processing data for view
$totalspent = $totaltime+$totalvendinv+$totalexpenses;
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
			<div class="budgettitle"><?php echo ($balance<0)?"Overspent":"Left to Spend"; ?> </div>
			<div class="famount" style='color:<?php echo $blncolor; ?>'>
				$<?php echo number_format(abs($balance));?>
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
					data: [<?php echo "$totaltime,$totalvendinv,$totalexpenses"; echo ($balance>0)?",$balance":""; ?>],
					backgroundColor: [window.chartColors.cyan,
										window.chartColors.pink,
										window.chartColors.yellow,
										window.chartColors.white,]
					}],
				labels: ['Time Spent on Tasks','Vendor Invoices','Staff Expenses'<?php echo ($balance>0)?",'Balance'":""; ?>]				
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




	<?php
	//clean up data
	asort($cleanmos);
	foreach ($cleanmos as $id=>$data) {
		$molabels[] = date("M'y",strtotime($data."-01"));
		$mobudgets[] = $mobudget[$data];
		$mospents[] = $mospent[$data];
	}	
	?>

	<div class="budgettitle">Budget vs Spent by Month</div>
	<div class="budgetbarchart">
	<canvas id="canvas_idgraphmonth"></canvas>
	</div>

	<script id="idgraphmonth">
	var color = Chart.helpers.color;
	var month_config = {
			type: 'bar',
			data: {
				datasets: [{
					label: 'Budget',
					data: <?php echo json_encode(array_values($mobudgets)); ?>,
					backgroundColor: color(window.chartColors.blue).alpha(0.4).rgbString(),
					borderColor: window.chartColors.blue,
					borderWidth: 1,				
					},
					{
					label: 'Spent',
					type: 'line',
					data: <?php echo json_encode(array_values($mospents)); ?>,
					backgroundColor: color(window.chartColors.red).alpha(0).rgbString(),
					borderColor: window.chartColors.red,
					borderWidth: 1,				
					}
					],
				labels: <?php echo json_encode(array_values($molabels)); ?>,				
				},
				
			options: {
				responsive: true,
				legend: {
					position: 'top',
				},
				title: {
					display: false,
					text: 'Budget vs Spent by Month'
				},
				animation: {
					animateScale: true,
					animateRotate: true
				},
			
				tooltips: {
				  mode: 'label',
				}, //end tooltips
				
				scales: {
					yAxes: [{
						ticks: {
							beginAtZero:true,
							userCallback: function(value, index, values) {
								// Convert the number to a string and splite the string every 3 charaters from the end
								value = value.toString();
								value = value.split(/(?=(?:...)*$)/);
								value = value.join(',');
								return value;
							}
						}
					}],
					xAxes: [{
						ticks: {
						}
					}]
				},  //end scales	
			
			}
		};

	var ctx = document.getElementById("canvas_idgraphmonth").getContext("2d");
	var chart = new Chart(ctx, month_config);
	</script>
</div>


</div>