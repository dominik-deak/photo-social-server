<!doctype html>
<html>
<head>
    <!-- load jquery and bootstrap off the internet via CDNs rather than install locally -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.1/jquery.min.js" ></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">


</head>
<body>
<div class="container">
    <div class="row">
        <div class="col-md-8">
            <h2>This form is used by jquery to make an ajax DELETE request to the server</h2>
            <form>
                <input type=text value="4" />
                <input type=submit id="btn">
            </form>
        </div>
    </div>
</div>
<script language="javascript">
    $(document).ready(function () {
        $('#btn').click(function (event) {
            event.preventDefault();
            $.ajax({
                url : "<?php echo base_url() ?>index.php/Test/index",
                type: "DELETE" // calls the index_delete() function in the controller
            }).done(function (data) {
                alert(data);
            })
        })
    })
</script>
</body>
</html>