<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Check whether user login or not, if not redirect to login
        if (!auth()->user()) {
            // Redirect to login page
            return redirect()->route('login');
        }

        $carts = Cart::join('products', 'products.id', '=', 'carts.product_id')
            ->select('carts.id', 'carts.product_id', 'products.product_name', 'products.product_price', 'carts.quantity', 'carts.created_at', 'carts.payment_id')
            ->where('carts.payment_id', '=', null)
            ->where('carts.user_id', '=', auth()->user()->id)
            ->orderBy('carts.created_at', 'desc')
            ->get();

        $carts->each(function ($cart) {
            $cart->total = $cart->product_price * $cart->quantity;
        });

        $totalPrice = $carts->sum('total');


        // dd($carts);

        return view('payment.cart', compact('carts', 'totalPrice'));
    }

    public function paymentIndex(){
        $carts = Cart::where('user_id', '=', auth()->user()->id)
            ->where('payment_id', '=', null)
            ->get();
        
        $carts -> each(function($cart){
            $cart->total = $cart->product->product_price * $cart->quantity;
        });

        $totalPrice = $carts->sum('total');

        // dd($carts, $totalPrice);

        return view('payment.payment', compact('totalPrice'));
    }

    /**
     * Store a newly created resource in Cart.
     */
    public function storeCart(Request $request)
    {
        try {
            Product::findOrFail($request->product_id)->id;
        } catch (ModelNotFoundException) {
            return redirect()->route('cart')->with('error', 'Product barcode not exist!');
        }

        // If product already exist in cart, update quantity
        $cartExist = Cart::where('user_id', '=', auth()->user()->id)
            ->where('product_id', '=', $request->product_id)
            ->where('payment_id', '=', null)
            ->first();
        if ($cartExist) {
            $cartExist->quantity++;
            if ($cartExist->save()) {
                return redirect()->route('cart')->with('success', 'Product added to cart successfully!');
            } else {
                return redirect()->route('cart')->with('error', 'Failed to add product to cart!');
            }
        }

        $cart = new Cart();
        $cart->user_id = auth()->user()->id;
        $cart->product_id = $request->product_id;

        if ($request->quantity == null) {
            $cart->quantity = 1;
        } else {
            $cart->quantity = $request->quantity;
        }

        if ($cart->save()) {
            return redirect()->route('cart')->with('success', 'Product added to cart successfully!');
        } else {
            return redirect()->route('cart')->with('error', 'Failed to add product to cart!');
        }
    }

    /**
     * Store a newly created resource in Payment.
     */
    public function storePayment(Request $request)
    {
        $payment = new Payment();
        $payment->total_price = $request->total_price;
        $payment->payment_method = $request->payment_method;
        $payment->cash_amount = $request->cash_amount;
        $payment->save();

        $carts = Cart::where('user_id', '=', auth()->user()->id)
            ->where('payment_id', '=', null)
            ->get();
        
        $carts -> each(function($cart) use ($payment){
            $cart->payment_id = $payment->id;
            $cart->save();
        });

        return redirect()->route('cart')->with('success', 'Payment success!');
    }


    /**
     * Display the specified resource.
     */
    public function show(Payment $payment)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Payment $payment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function incrementQuantity($id)
    {
        $cart = Cart::find($id);
        if (!$cart) {
            return redirect()->back()->with('error', 'Product not found.');
        }

        $cart->quantity++;

        if ($cart->save()) {
            return redirect()->back()->with('success', 'Quantity updated successfully.');
        } else {
            return redirect()->back()->with('error', 'Failed to update quantity.');
        }
    }

    public function decrementQuantity($id)
    {
        $cart = Cart::find($id);
        if (!$cart) {
            return redirect()->back()->with('error', 'Product not found.');
        }

        if ($cart->quantity == 1) {
            PaymentController::destroyCart($id);
            return redirect()->back()->with('success', 'Product deleted from cart!');
        } else {
            $cart->quantity--;
        }

        if ($cart->save()) {
            return redirect()->back()->with('success', 'Quantity updated successfully.');
        } else {
            return redirect()->back()->with('error', 'Failed to update quantity.');
        }
    }


    /**
     * Remove the specified resource from Cart.
     */
    public function destroyCart($id)
    {
        $cart = Cart::find($id);
        if (!$cart) {
            return redirect()->route('cart')->with('error', 'Cannot delete product!');
        }

        if ($cart->delete()) {
            return redirect()->route('cart')->with('success', 'Product deleted from cart successfully!');
        } else {
            return redirect()->route('cart')->with('error', 'Failed to delete product from cart!');
        }
    }

    /**
     * Remove the all from Cart.
     */
    public function destroyAll()
    {
        $carts = Cart::where('user_id', '=', auth()->user()->id)
            ->where('payment_id', '=', null)
            ->get();

        // dd($carts);

        foreach ($carts as $cart) {
            $cart->delete();
        }

        return redirect()->route('cart')->with('success', 'All product deleted from cart successfully!');
    }
}