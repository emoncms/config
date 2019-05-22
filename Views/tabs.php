<ul class="nav nav-tabs mb-2 mt-2" id="backup-tabs">
    <?php 
    if(!empty($items)):foreach($items as $item): 
        echo $item; 
    endforeach;endif;
    ?>
</ul>