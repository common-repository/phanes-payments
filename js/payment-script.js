jQuery(function($){

    $("#copy-store-address").click(function (e) {
        var $temp = $("<input>");
        $("body").append($temp);
        $temp.val($("#store-address-input").val()).select();
        document.execCommand("copy");
        $temp.remove();
    });

    //SET TIME COUNTDOWN
    var paymentInitiatedDate = new Date($("#order-date-time").val()).getTime(); //timestamp
    // Set the date we're counting down to
    var countDownDate = new Date($("#order-date-time").val());
    countDownDate.setMinutes(countDownDate.getMinutes() + 10);
    countDownDate = countDownDate.getTime(); //timestamp
    var paymentExpired = false;

    // Update the count down every 1 second
    var x = setInterval(function() {

      // Get today's date and time
      var now = new Date().getTime();

      // Find the distance between now and the count down date
      var distance = countDownDate - now;

      // Time calculations for days, hours, minutes and seconds
    //   var days = Math.floor(distance / (1000 * 60 * 60 * 24));
    //   var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
      var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
      var seconds = Math.floor((distance % (1000 * 60)) / 1000);

      // Display the result in the element with id="demo"
      $("#transaction-timer").text("00:" + minutes + ":" + seconds);

      // If the count down is finished, write some text
      if (distance < 0) {
        clearInterval(x);
        paymentExpired = true;
        var orderID = $("#phanes-order-id").val();
        expireOrder(orderID);
        $("#transaction-timer").text("EXPIRED");
        
      }
    }, 1000);

    //check payment every 15s seconds
    var checker = setInterval(function () {
        if (!paymentExpired) {
            //check payment
            var selectedCoin = $("#phanes-selected-coin").val();
            var storeAddress = skStoreAddress(selectedCoin);
            var senderAddress = $("#order-sender-crypto-address").val();
            var cryptoAmount = parseFloat($("#order-crypto-amount").val());
            var orderID = $("#phanes-order-id").val();
            //check TRX in TRC10
            if (selectedCoin == 'trx') {
              $.ajax({
                // url: "https://apilist.tronscan.org/api/transfer?address=" + storeAddress + "&start_timestamp=" + paymentInitiatedDate + "&end_timestamp=" + countDownDate,
                url: "https://apilist.tronscan.org/api/transfer?address=" + storeAddress + "&limit=10",
                contentType: "application/json",
                dataType: 'json',
                success: function(result){
                    var data = result.data;
                    for (var i = 0; i < data.length; i++) {
                      var transfer = data[i];
                      var transferAmount = parseFloat(transfer.amount) / parseInt(decZeros(parseInt(transfer.tokenInfo.tokenDecimal)));
                      if (transfer.transferFromAddress == senderAddress
                          && transfer.transferToAddress == storeAddress 
                          && (transferAmount >= cryptoAmount && transferAmount <= (cryptoAmount + 2))
                          && transfer.tokenInfo.tokenAbbr == "trx" 
                          && (transfer.confirmed == "true" || transfer.confirmed == true) 
                          && transfer.contractRet == "SUCCESS") {
                            confirmOrder(transfer.transactionHash, orderID);
                            clearInterval(x);
                            $("#transaction-timer").text("Loading...");
                            
                      }
                      
                    }
                },
                error: function() {
                  if (!senderAddress) return; //to prevent alert on other pages  
                  alert("Unable to confirm payment! Try checking your network connectivity.");
                }
              });
            }
            //check PHUCK in TRC20
            if (selectedCoin == 'phuck') {
              $.ajax({
                // url: "https://apilist.tronscan.org/api/contract/events?address=" + storeAddress + "&start_timestamp=" + paymentInitiatedDate + "&end_timestamp=" + countDownDate,
                url: "https://apilist.tronscan.org/api/contract/events?address=" + storeAddress + "&limit=10",
                contentType: "application/json",
                dataType: 'json',
                success: function(result){
                    var data = result.data;
                    for (var i = 0; i < data.length; i++) {
                      var transfer = data[i];
                      var transferAmount = parseFloat(transfer.amount) / parseInt(decZeros(parseInt(transfer.decimals)));
                      if (transfer.transferFromAddress == senderAddress
                          && transfer.transferToAddress == storeAddress 
                          && (transferAmount >= cryptoAmount && transferAmount <= (cryptoAmount + 2))
                          && transfer.tokenName == "PHUCKS" 
                          && (transfer.confirmed == "true" || transfer.confirmed == true)) {
                            confirmOrder(transfer.transactionHash, orderID);
                            clearInterval(x);
                            $("#transaction-timer").text("Loading...");
                      }
                    }
                },
                error: function() {
                  if (!senderAddress) return; //to prevent alert on other pages
                  alert("Unable to confirm payment! Try checking your network connectivity.");
                }
              });
            }
            
            
            
        }
    }, 15000);

  
    function confirmOrder(hash_code, order_id) {
      $.ajax({
        url: phuck_payment_params.ajax_url, // this is the object instantiated in wp_localize_script function
        type: 'POST',
        data:{ 
          action: 'phuck_order_action', // this is the function in your functions.php that will be triggered
          hash_code: hash_code,
          order_id: order_id
        },
        success: function( data ){
          if (data == "success") {
            $("#transaction-timer").text("CONFIRMED");
          } else {
            $("#transaction-timer").text("Can't update order! Try reload");
          }
        },
        error: function() {
          $("#transaction-timer").text("ERROR! Try reload");
        }
      });
    }
    function expireOrder(order_id) {
      $.ajax({
        url: phuck_payment_params.ajax_url, // this is the object instantiated in wp_localize_script function
        type: 'POST',
        data:{ 
          action: 'phuck_order_expire_action', // this is the function in your functions.php that will be triggered
          order_id: order_id
        },
        success: function( data ){
          console.log(data);
        },
        error: function() {
          console.log("Can't update order to pending!");
        }
      });
    }
    function skStoreAddress(slug) {
      switch(slug) {
        case 'phuck':
          return phuck_payment_params.phuckAddress;
        case 'trx':
          return phuck_payment_params.trxAddress;
        default:
          return 'error-coin';
      }
    }

});

//functions
function decZeros(size) {
  var s = 1 + "";
    while (s.length <= size) s = s + "0";
    return s;
}

function skCoinChange(e) {
  var coin = jQuery(e);
  var option = jQuery('option:selected', coin);
  jQuery("#crypto-wallet-name").text(option.attr('aria-valuetext')); //crypto name
  jQuery("#amount-of-crypto-to-pay").val(option.attr("aria-details")); //amount of crypto to pay
  jQuery("#current-crypto-price").val(option.attr("aria-current")); // current crypto price in usd
}