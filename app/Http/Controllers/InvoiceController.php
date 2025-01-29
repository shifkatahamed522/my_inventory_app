<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceProduct;
use App\Models\Product;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class InvoiceController extends Controller
{

    function InvoiceListPage(Request $request)
    {
        $user_id=$request->header('id');
        $list=Invoice::where('user_id',$user_id)->with('customer','invoiceProduct.product')->get();
        return Inertia::render('InvoiceListPage',['list'=>$list]);
    }

    function SalePage(Request $request)
    {
        $user_id=$request->header('id');
        $customer=Customer::where('user_id',$user_id)->get();
        $product=Product::where('user_id',$user_id)->get();
        return Inertia::render('SalePage', ['customer'=>$customer , 'product'=>$product]);
    }




    function invoiceCreate(Request $request){
        
        DB::beginTransaction();
          try{
            $userId=$request->header('id');
            $data=[
                'user_id'=>$userId,
                'customer_id'=>$request->input('customer_id'),
                'total'=>$request->input('total'),
                'vat'=>$request->input('vat'),
                'payable'=>$request->input('payable'),
                'discount'=>$request->input('discount'),
            ];

            $invoice=Invoice::create($data);

            $products=$request->input('product');
            foreach($products as $product){
                InvoiceProduct::create([
                    'invoice_id'=>$invoice->id,
                    'product_id'=>$product['id'],
                    'user_id'=>$userId,
                    'qty'=>$product['unit'],
                    'sale_price'=>$product['price'],
                    $exitsUnit=Product::where('id','=',$product['id'])->select('unit')->first(),
                    Product::where('id','=',$product['id'])->update(['unit'=>$exitsUnit->unit-$product['unit']])
                ]);
                
            }
            DB::commit();
            return redirect()->route('InvoiceListPage')->with(['status'=>true, 'message'=>"Success"]);

            }
            catch (Exception $e) {
                DB::rollBack();
                return redirect()->route('SalePage')->with(['status'=>false, 'message'=>"Fail"]);
            }


    }


    function invoiceSelect(Request $request){
        $user_id=$request->header('id');
        return Invoice::where('user_id',$user_id)->with('customer')->get();
    }

    function InvoiceDetails(Request $request){
        $user_id=$request->header('id');
        $customerDetails=Customer::where('user_id',$user_id)->where('id',$request->input('cus_id'))->first();
        $invoiceTotal=Invoice::where('user_id','=',$user_id)->where('id',$request->input('inv_id'))->first();
        $invoiceProduct=InvoiceProduct::where('invoice_id',$request->input('inv_id'))
            ->where('user_id',$user_id)->with('product')
            ->get();
        return array(
            'customer'=>$customerDetails,
            'invoice'=>$invoiceTotal,
            'product'=>$invoiceProduct,
        );

    }


    function invoiceDelete(Request $request) {
     
        $id = $request->id ;

        DB::beginTransaction();
        try {
            $user_id = $request->header('id');
            
            // Deleting from invoice_products based on invoice_id and user_id
            InvoiceProduct::where('invoice_id', $id)
                ->where('user_id', $user_id)
                ->delete();
            
            // Deleting the invoice itself
            Invoice::where('id', $id)
                ->where('user_id', $user_id)
                ->delete();
    
            // Commit the transaction after the delete operations
            DB::commit();
            
            $data = ['message' => 'Delete Successful', 'status' => true, 'error' => ''];
            return redirect()->route('InvoiceListPage')->with($data);
            
        } catch (Exception $e) {
            // If there's any issue, rollback the transaction
            DB::rollBack();
            
            $data = ['message' => 'Delete Failed', 'status' => false, 'error' => $e->getMessage()];
            return redirect()->route('InvoiceListPage')->with($data);
        }
    }
    
    
}
