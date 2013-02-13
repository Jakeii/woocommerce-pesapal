---
layout: page
title: Pesapal Plugin for Woocommerce beta
---
{% include JB/setup %}

<div class="hero-unit">
  <h1>Pesapal Plugin for Woocommerce beta</h1>
  <p>Simple and easy to use plugin for <a href="http://pesapal.com">pesapal.com</a> payment gateway. Version 0.0.1</p>
  <p>
    <a class="btn btn-primary btn-large" href="https://github.com/Jakeii/woocommerce-pesapal">
      Learn more on Github <span class="icon-right-circled"></span>
    </a>
    <a class="btn btn-success btn-large" href="https://github.com/Jakeii/woocommerce-pesapal/archive/master.zip">
      Download <span class="icon-download"></span>
    </a>
    <a id="donatePesapal" class="btn btn-warning btn-large" href="#">Donate :)</a>
  </p>
  <div class="row"></div>
  <div id="donateForm" class="well span6" style="display:none;">
    <h2>Donate</h2>
    <p class="lead">Thank you very much for donating!</p>
    <form class="form-horizontal" method="post" action="http://pesapal.donate.bodhi.io">
      <div class="control-group">
        <label class="control-label" for="inputEmail">Email</label>
        <div class="controls">
          <input name="email" type="text" id="inputEmail" placeholder="Email" />
        </div>
      </div>
      <div class="control-group">
        <label class="control-label" for="inputAmount">Amount</label>
        <div class="controls">
          <input name="amount" id="inputAmount" placeholder="Amount" />
        </div>
      </div>
      <div class="control-group">
        <label class="control-label">Currency</label>
        <div class="controls">
          <select name="currency" id="inputCurrency">
            <option value="KES" selected="true">Kenyan Shillings</option>
            <option value="USD">US Dollars</option>
          </select>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-large btn-block">Donate</button>
    </form>
  </div>
  <!-- <p>
       <a class="btn btn-info btn-small">
      Donate with mPesa/pesapal <span class="icon-credit-card"></span>
    </a>
    <a class="btn btn-info btn-small">
      Donate with Paypal <span class="icon-credit-card"></span>
    </a>
  </p> -->
</div>


