
const Oney_Simulation = ( {settings, name, props} ) => {

	const translations = settings?.translations;
	var down_payment_amount = parseFloat(settings?.oney_response[name]['down_payment_amount']);

	var total_price_oney = down_payment_amount;
	settings?.oney_response[name]['installments'].forEach((amount) => {
		total_price_oney += parseFloat(amount['amount']);
	});

	let has_3rd_payment = typeof translations['3rd_monthly_payment'] != "undefined";
	return (
		<>
			<div>
				<div className="payplug-oney-flex">
					<div>{translations['bring']} :</div>
					<div>{down_payment_amount} {props.billing.currency.symbol}</div>
				</div>
				<div className="payplug-oney-flex">
					<small>( {translations['oney_financing_cost']}
						<b>{settings?.oney_response[name]['total_cost']} {props.billing.currency.symbol}</b> TAEG
						: <b>{settings?.oney_response[name]['effective_annual_percentage_rate']} %</b> )</small>
				</div>
				<div className="payplug-oney-flex">
					<div>{translations['1st_monthly_payment']}:</div>
					<div>{settings?.oney_response[name]['installments'][0]['amount']} {props.billing.currency.symbol}</div>
				</div>
				<div className="payplug-oney-flex">
					<div>{translations['2nd_monthly_payment']}:</div>
					<div>{settings?.oney_response[name]['installments'][1]['amount']} {props.billing.currency.symbol}</div>
				</div>
				{ has_3rd_payment ?
					<div className="payplug-oney-flex">
						<div>{translations['3rd_monthly_payment']}:</div>
						<div>{settings?.oney_response['x4_without_fees']['installments'][2]['amount']} {props.billing.currency.symbol}</div>
					</div> : <></>
				}
				<div className="payplug-oney-flex">
					<div><b>{translations['oney_total']}</b></div>
					<div><b>{total_price_oney.toFixed(2)} {props.billing.currency.symbol}</b></div>
				</div>
			</div>
		</>
	);


}


export default Oney_Simulation;
