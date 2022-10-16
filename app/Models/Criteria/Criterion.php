<?php

namespace App\Models\Criteria;

use Config;
use App\Models\Model;

class Criterion extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'currency_id', 'is_active', 'summary', 'is_guide_active', 'base_value'
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'criteria';
    
    /**
     * Validation rules for creation.
     *
     * @var array
     */
    public static $createRules = [
        'name' => 'required|unique:character_categories|between:3,100',
        'currency_id' => 'required'
    ];
    
    /**
     * Validation rules for updating.
     *
     * @var array
     */
    public static $updateRules = [
        'name' => 'required|between:3,100',
    ];

    /**********************************************************************************************
    
        RELATIONS

    **********************************************************************************************/
    
    /**
     * Get the currency for this criterion.
     */
    public function currency() 
    {
        return $this->belongsTo('App\Models\Currency\Currency', 'currency_id');
    }
    
    /**
     * Get all steps associated with the criterion.
     */
    public function steps()
    {
        return $this->hasMany('App\Models\Criteria\CriterionStep', 'criterion_id');
    }
    
    /**********************************************************************************************
    
        ACCESSORS

    **********************************************************************************************/
    
    /**
     * Displays the model's name, linked to its encyclopedia page.
     *
     * @return string
     */
    public function getDisplayNameAttribute()
    {
        return $this->is_guide_active ? '<a href="'.url('criteria/guide/'.$this->id).'" class="display-species">'.$this->name.'</a>' : $this->name;
    }
    
    
     /**********************************************************************************************

        SCOPES

    **********************************************************************************************/
    
    /**
     * Scope a query to only include visible criteria.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
    
    
    /**********************************************************************************************

        METHODS

    **********************************************************************************************/
    
    public function calculateReward($stepResults) {
        
        $total = $this->base_value ?? 0;
        foreach($this->steps->where('is_active', 1) as $step) {
            $subTotal = 0;
            // Get our subtotal from the step type and options
            if($step->type === 'boolean' && isset($stepResults[$step->id])) {
                $subTotal = $step->options->first()->amount;
            } else if ($step->type === 'input') {
                $subTotal = $step->options->first()->amount;
                $input = floatval($stepResults[$step->id]);
                if($step->input_calc_type === 'additive') {
                    $subTotal += $input;
                } else if($step->input_calc_type === 'multiplicative') {
                    $isDivision = $input < 0;
                    if(!$isDivision) $subTotal *= $input;
                    elseif ($input === 0 && $isDivision) throw new \Exception ("Criterion attempted to divide by zero.");
                    else $subTotal /= $input;
                }
            } else if($step->type === 'options') {
                $optionId = $stepResults[$step->id];
                $subTotal = $step->options()->where('id', $optionId)->first()->amount;
            }
            
            // Apply subtotal to running total based on calc type
            if($step->calc_type === 'additive') {
                $total += $subTotal;
            } else if($step->calc_type === 'multiplicative') {
                $isDivision = $subTotal < 0;
                // makes sure we don't zero out the equation if we have an unset multiplicative boolean
                if(!$isDivision) $total *= max($subTotal, 1);
                elseif ($subTotal === 0 && $isDivision) throw new \Exception ("Criterion attempted to divide by zero.");
                else $total /= $subTotal;
            }
        }
        
        return $total;
    }
  }