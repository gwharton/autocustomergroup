<h1>AutoCustomerGroup</h1>
<p>Magento 2 Module - Auto Assign Customer Group based on Tax Scheme validation</p>

<p>Changes introduced to both the UK and EU VAT Tax systems require changes to be made to the Magento Tax system. These changes are required URGENTLY, and while Magento consider the changes required and work towards a permanent solution, this module can be used as an interim measure.</p>

<p>The module should be considered BETA. I encourage users to analyse the code, suggest improvements, generate PR's where applicable.</p>

<p>The module completely replaces the current Magento 2 VIV subsystem. The old settings are removed from the admin panel and replaced with a new Admin screen.</p>

<img src="images/menu.png">

<h2>General</h2>
<img src="images/general.png">
<ul>
<li><b>Default Group</b> - This is the default group that customers will be assigned to if they do not have a group assigned. Note that guest users are always assigned to the "NOT LOGGED IN" group.</li>
<li><b>Enable Automatic Assignment to Customer Group</b> - This activates and deactivates the module. When turned off, all orders will be placed either in the "NOT LOGGED IN" group for guests, or the Default Group/Customer Group for logged in customers.</li>
<li><b>Validate on Each Transaction</b> - If the order is being placed by a customer that has existing Tax ID Validation data stored in their shipping address, then this can be re-used on each subsequent order, or it can be revalidated every time.</li>
</ul>
<h2>UK VAT Scheme</h2>
<img src="images/ukvat.png">
<ul>
<li><b>Enabled</b> - Enable/Disable this Scheme.</li>
<li><b>Environment</b> - Whether to use the Sandbox or Production servers for the HMRC VAT Validation Service.</li>
<li><b>Client ID</b> - Client ID as provided by HMRC Developer Portal.</li>
<li><b>Client Secret</b> - Client Secret as provided by HMRC Developer Portal.</li>
<li><b>VAT Registration Number</b> - The UK VAT Registration Number for the Merchant. This will be provided to HMRC when all validation checks are made.</li>
<li><b>Import VAT Threshold</b> - The order value (ex VAT) threshold (in Store Currency) above which no VAT should be charged. Calculated as the sum of all line items after discount ex Tax.</li>
<li><b>Customer Group - UK Import (Above Threshold)</b> - Merchant Country is not within the UK/Isle of Man, Item is being shipped to the UK/Isle of Man, Order Value is above Tax Threshold.</li>
<li><b>Customer Group - UK Import (Valid VAT No.)</b> - Merchant Country is not within the UK/Isle of Man, Item is being shipped to the UK/Isle of Man, VAT Number Validated Successfully.</li>
</ul>

<h2>EU VAT Scheme</h2>
<img src="images/euvat.png">
<ul>
<li><b>Enabled</b> - Enable/Disable this Scheme.</li>
<li><b>VAT Registration Country</b> - The country in which the Merchant is VAT Registered. This will be provided to HMRC when all validation checks are made.</li>
  <li><b>VAT Registration Number</b> - The EU VAT Registration Number for the Merchant. This will be provided to HMRC when all validation checks are made.</li>
<li><b>Import VAT Threshold</b> - The order value (ex VAT) threshold (in Store Currency) above which no VAT should be charged. Calculated as the sum of all line items after discount ex Tax.</li>
<li><b>Customer Group - EU Import (Above Threshold)</b> - Merchant Country is not within the EU, Item is being shipped to the EU, Order Value is above Tax Threshold.</li>
<li><b>Customer Group - Intra-EU (Valid VAT No.)</b> - Merchant Country is within the EU, Item is being shipped to the EU, Merchant Country and Shipping Country are not the same, VAT Number Validated Successfully.</li>
<li><b>Customer Group - EU Import (Valid VAT No.)</b> - Merchant Country is not within the EU, Item is being shipped to the EU, VAT Number Validated Successfully.</li>
</ul>
