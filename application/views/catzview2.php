 <!DOCTYPE html>
    <html lang="en">
    <head>
        <title>iLikeCatz</title>
		<!-- Latest compiled and minified CSS -->
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css">
		
		<style>
			body { padding-top: 70px; } /* needed to position navbar properly */
		</style>
    </head>
    <body>
		<div class="container">
			<nav class="navbar navbar-default navbar-fixed-top navbar-inverse">
			  <div class="container">
				<div class="container-fluid">
				    <div class="navbar-header">
				      <p class="navbar-brand">
				        iLikeCatz
				      </p>
				    </div>
				  </div>
			  </div>
			</nav>
			<div class='row'>
					<div class="col-md-8 col-md-offset-4">
	        			<h1>I Like Catz!</h1>
	       			 	<img id='catimg' class="img-rounded" src="<?php echo $cat ?>" height=200px />
					</div> <!-- col-md-8 -->
					<div class="col-md-8 col-md-offset-4" style="margin-top:20px">
						<button class="btn btn-sm btn-primary" id="nextcatbtn">Next cat</button>
					</div>
			</div> <!-- row -->
		</div> <!-- container -->
		
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script> 
		
		<script language="Javascript">		
			$('#nextcatbtn').click(function () {
				var request = $.ajax({
					url: "<?php echo base_url() ?>index.php/Catz/geturl",
					method: "GET"
				});
				
				request.done(function(catimg) {
					$('#catimg').attr('src',catimg);
							
				});
				
				request.fail(function (jqxhr,textstatus,error) {
				    alert(textstatus);	
				});

			});
		</script>
			
    </body>
    </html>