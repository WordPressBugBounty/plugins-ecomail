jQuery(document).ready((function(o){var t,e=function(){var t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:null,e=arguments.length>1&&void 0!==arguments[1]?arguments[1]:null;o.ajax({url:ecomailArgs.restUrl+"/cart",method:"post",data:{email:e,items:t},success:function(o){console.log(o)}},"json")};ecomailArgs.cartTrackingEnabled&&(o(document.body).on("added_to_cart",(function(){e()})),o(document.body).on("removed_from_cart",(function(){e()})),o(document.body).on("cart_totals_refreshed",(function(){e()})),"undefined"!=typeof ecomailCart&&e(ecomailCart.items),o("input#billing_email").on("change",(function(){if(ecomailArgs.emailExists)return!1;var t=o(this).val();(function(o){return/^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/.test(String(o).toLowerCase())})(t)&&e(null,t)}))),ecomailArgs.lastProductTrackingEnabled&&ecomailArgs.productId&&(t=ecomailArgs.productId,o.ajax({url:ecomailArgs.restUrl+"/product",method:"post",data:{product_id:t},success:function(o){console.log(o)}},"json"))}));