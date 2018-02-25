<?php
	include("lib/func.php");

	$data = getData();
	foreach ($data as $name => $card)
	{
		generateCard($name, $card, "default.png", 10, 10);
	}

	echo "Successfully created!";
?>