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
    <div class="btn-group">
      <a id="donate" class="btn btn-warning btn-large dropdown-toggle" data-toggle="dropdown" href="#">
        Donate <span class="icon-down-open"></span>
      </a>
      <ul class="dropdown-menu" role="menu" aria-labelledby="dLabel">
       <li><a id="donatePesapal" tabindex="-1" href="#">By Pesapal/mPesa</a></li>
       <li><a tabindex="-1" href="#">By Paypal</a></li>      
      </ul>
    </div>
  </p>
  <div id="donateForm" class="well" style="display:none;">
    <h1>Donate</h1>
    <p class="lead">Thank you very much for donating!</p>
    <form class="form-horizontal">
      <div class="control-group">
        <label class="control-label" for="inputEmail">Email</label>
        <div class="controls">
          <input name="email" type="text" id="inputEmail" placeholder="Email">
        </div>
      </div>
      <div class="control-group">
        <label class="control-label" for="inputAmount">Amount</label>
        <div class="controls">
          <input name="amount" id="inputAmount" placeholder="Amount">
        </div>
      </div>
      <div class="control-group">
        <label class="control-label">Currency</label>
        <div class="controls">
          <select name="currency" id="inputCurrency">
            <option value="KES" selected>Kenyan Shillings</option>
            <option value="USD">US Dollars</option>
            <option value="GBP">British Pound</option>
            <option value="EUR">Euro</option>
          </select>
        </div>
      </div>
      <button type="submit" class="btn">Donate</button>
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


