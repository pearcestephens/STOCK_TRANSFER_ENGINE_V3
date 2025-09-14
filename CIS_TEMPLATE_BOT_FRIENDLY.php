<?php 
/**
 * ========================================
 * 🤖 BOT-FRIENDLY CIS TEMPLATE STRUCTURE
 * ========================================
 * 
 * FOR AI ASSISTANTS: This is the EXACT template structure you MUST follow
 * when creating pages for the CIS (Central Information System).
 * 
 * ⚠️  CRITICAL RULES FOR BOTS:
 * 1. NEVER change this structure
 * 2. ONLY put your content between the marked sections
 * 3. ALL CSS must be scoped to avoid conflicts
 * 4. Follow this pattern EXACTLY
 * 
 * @author Pearce Stephens / Ecigdis Ltd
 * @template CIS Standard Page Template
 * @bot_instructions Follow this structure religiously
 */

// ========================================
// 🔧 STEP 1: INCLUDES (NEVER CHANGE THESE)
// ========================================
include("assets/functions/config.php");

// ========================================
// 🔧 STEP 2: HANDLE POST REQUESTS (YOUR BACKEND LOGIC GOES HERE)
// ========================================
// 🤖 BOT INSTRUCTION: Put all your AJAX/POST handling here
// Example POST handler (replace with your own):
if (isset($_POST["updateBrandTransferStatus"])){
  updateBrandTransferStatus($_POST["updateBrandID"],$_POST["updateBrandTransferStatus"]);
  die();
}

// ========================================
// 🔧 STEP 3: TEMPLATE HEADERS (NEVER CHANGE THESE)
// ========================================
include("assets/template/html-header.php");
include("assets/template/header.php");

// ========================================
// 🔧 STEP 4: PAGE DATA SETUP (YOUR PHP LOGIC GOES HERE)
// ========================================
// 🤖 BOT INSTRUCTION: Put all your database queries and data preparation here
// Example data setup (replace with your own):
$brands = getAllVendBrands();
$automaticStoreTransfers = getConfigValueByLabel("automatic_store_transfers_active");

?>

<!-- ========================================
     🎨 STEP 5: HTML BODY START (NEVER CHANGE)
     ======================================== -->
<body class="app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show">
   
<div class="app-body">
    
    <!-- ========================================
         🔧 STEP 6: SIDE MENU (NEVER CHANGE)
         ======================================== -->
    <?php include("assets/template/sidemenu.php") ?>
    
    <!-- ========================================
         🎨 STEP 7: MAIN CONTENT AREA BEGINS
         ======================================== -->
    <main class="main">
        
        <!-- ========================================
             🧭 STEP 8: BREADCRUMB (CUSTOMIZE THIS)
             ======================================== -->
        <!-- 🤖 BOT INSTRUCTION: Change the breadcrumb items to match your page -->
        <ol class="breadcrumb">
            <li class="breadcrumb-item">Home</li>
            <li class="breadcrumb-item">
                <a href="#">Admin</a>
            </li>
            <li class="breadcrumb-item active">Brands</li>
            
            <!-- Breadcrumb Menu (NEVER CHANGE) -->
            <li class="breadcrumb-menu d-md-down-none">
                <?php include('assets/template/quick-product-search.php');?>
            </li>
        </ol>
        
        <!-- ========================================
             📦 STEP 9: MAIN CONTAINER (NEVER CHANGE STRUCTURE)
             ======================================== -->
        <div class="container-fluid">
            <div class="animated fadeIn">
                <div class="row">         
                    <div class="col">
                        
                        <!-- ========================================
                             🎯 YOUR CONTENT GOES HERE (REPLACE THIS ENTIRE CARD)
                             ======================================== -->
                        <!-- 🤖 BOT INSTRUCTION: Replace this entire card div with your content -->
                        <div class="card">
                            
                            <!-- Card Header -->
                            <div class="card-header">
                                <h4 class="card-title mb-0">Brands</h4>
                                <div class="small text-muted">Brands that are brought across from VEND. Specific CIS settings can be adjusted here.</div> 
                            </div>
                            
                            <!-- Card Body - YOUR MAIN CONTENT AREA -->
                            <div class="card-body negative-data">
                                
                                <!-- Example Alert (replace with your content) -->
                                <?php if ($automaticStoreTransfers == 0){ ?>
                                    <div class="alert alert-danger" role="alert">
                                        Automatic Store Transfers are currently Disabled
                                    </div>
                                <?php } ?>
                                
                                <!-- Example Note (replace with your content) -->
                                <p style="margin:0;padding:0">
                                    Please Note: Supplier Settings take precedence over brand settings.
                                </p>
                                
                                <!-- Example Table (replace with your content) -->
                                <table class="table table-responsive-sm table-bordered table-striped table-sm" id="negative-count">
                                    <thead>
                                        <tr>
                                            <th>Brand Name</th>
                                            <th title="Any Brand turned off will not go out in the automatic weekly transfers.">
                                                Store Transfers Enabled
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($brands as $b){ ?>
                                        <tr>
                                            <td><?php echo $b->name;?></td>
                                            <td>
                                                <label class="switch"> 
                                                    <input <?php if ($automaticStoreTransfers == 0){echo "disabled";}?> 
                                                           type="checkbox" 
                                                           brandid="<?php echo $b->id;?>" 
                                                           <?php echo $b->enable_store_transfers == 1 ? "checked='true' enabled='1'" : "enabled='0'";?>> 
                                                    <span class="store-transfer-switch slider round"></span> 
                                                </label>
                                            </td>
                                        </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                                
                            </div>
                            <!-- END Card Body -->
                            
                        </div>
                        <!-- END Card - REPLACE EVERYTHING ABOVE THIS COMMENT -->
                        
                    </div>
                    <!-- END col -->
                </div>
                <!-- END row -->
            </div>
            <!-- END animated fadeIn -->
        </div>
        <!-- END container-fluid -->
    </main>
    <!-- END main -->

    <!-- ========================================
         🔧 STEP 10: PERSONALIZATION MENU (NEVER CHANGE)
         ======================================== -->
    <?php include("assets/template/personalisation-menu.php") ?>
    
</div>
<!-- END app-body -->

<!-- ========================================
     🔧 STEP 11: TEMPLATE FOOTERS (NEVER CHANGE)
     ======================================== -->
<?php include("assets/template/html-footer.php") ?>
<?php include("assets/template/footer.php") ?>

<!-- ========================================
     🎨 STEP 12: YOUR JAVASCRIPT GOES HERE
     ======================================== -->
<!-- 🤖 BOT INSTRUCTION: Put all your JavaScript in this script tag -->
<script>
// Example JavaScript (replace with your own)
$('.store-transfer-switch').click(function(){
    var checkbox = $(this.parentElement).find("input")[0];
    var brandID = $(checkbox).attr("brandid");

    if (!checkbox.disabled){
        if (checkbox.checked){
            $(checkbox).attr("enabled","0");
            createTransferStatusChange(brandID,0);
        }else{
            $(checkbox).attr("enabled","1");
            createTransferStatusChange(brandID,1);
        }
    }else{
        alert("Disabled");
    }
});

function createTransferStatusChange(brandID,status){
    $.post("",{
        updateBrandID: brandID,
        updateBrandTransferStatus: status
    });
}
</script>

<!-- ========================================
     🎨 STEP 13: YOUR CSS GOES HERE
     ======================================== -->
<!-- 🤖 BOT INSTRUCTION: Put all your CSS in this style tag -->
<!-- ⚠️  CRITICAL: Scope your CSS to avoid conflicts! -->
<style>
/* Example CSS - NOTICE how it's scoped to specific classes */
.switch {
    position: relative;
    display: inline-block;
    width: 53px;
    height: 21px;
    margin:0;
}

/* Hide default HTML checkbox */
.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

/* The slider */
.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    -webkit-transition: .4s;
    transition: .4s;
}

.slider:before {
    position: absolute;
    content: "";
    height: 15px;
    width: 18px;
    left: 4px;
    bottom: 3px;
    background-color: white;
    -webkit-transition: .4s;
    transition: .4s;
}

input:checked + .slider {
    background-color: #2196F3;
}

input:focus + .slider {
    box-shadow: 0 0 1px #2196F3;
}

input:checked + .slider:before {
    -webkit-transform: translateX(26px);
    -ms-transform: translateX(26px);
    transform: translateX(26px);
}

/* Rounded sliders */
.slider.round {
    border-radius: 34px;
}

.slider.round:before {
    border-radius: 50%;
}
</style>

<!-- ========================================
     🤖 END OF TEMPLATE
     ========================================
     
     SUMMARY FOR AI ASSISTANTS:
     
     1. Copy this entire template
     2. Replace ONLY the card content (Step 9)
     3. Update breadcrumb (Step 8)
     4. Add your POST handlers (Step 2)
     5. Add your data setup (Step 4)
     6. Add your JavaScript (Step 12)
     7. Add your scoped CSS (Step 13)
     
     NEVER CHANGE: Steps 1,3,5,6,7,10,11
     
     ======================================== -->
