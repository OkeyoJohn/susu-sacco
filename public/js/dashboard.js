
const API_URL = "../api/report.php";

const money = (amount) =>
    "KSh " + Number(amount).toLocaleString("en-KE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });

async function loadDashboard() {
    const message = document.getElementById("dashboard-message");

    try {
        const response = await fetch(API_URL);
        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || "Could not load dashboard.");
        }

        document.getElementById("current-week").textContent =
            data.current_week;

        document.getElementById("week-status").textContent =
            "Status: " + data.week_status;

        document.getElementById("member-count").textContent =
            data.member_count;

        document.getElementById("weekly-amount").textContent =
            money(data.weekly_contribution_kes);

        document.getElementById("collected-amount").textContent =
            money(data.current_week_collected_kes);

        document.getElementById("next-recipient").textContent =
            data.next_recipient || "No recipient available";

        document.getElementById("total-payouts").textContent =
            money(data.total_payouts_kes);

        document.getElementById("payout-count").textContent =
            `${data.payout_count} payout(s) recorded`;

        const history = document.getElementById("payout-history");
        history.replaceChildren();

        if (data.payout_history.length === 0) {
            const row = document.createElement("tr");
            const cell = document.createElement("td");

            cell.colSpan = 4;
            cell.textContent = "No payouts recorded yet.";

            row.appendChild(cell);
            history.appendChild(row);
            return;
        }

        data.payout_history.forEach((payout) => {
            const row = document.createElement("tr");

            const values = [
                `Week ${payout.week_number}`,
                payout.recipient_name,
                money(payout.amount_kes),
                payout.paid_at || "Date unavailable"
            ];

            values.forEach((value) => {
                const cell = document.createElement("td");
                cell.textContent = value;
                row.appendChild(cell);
            });

            history.appendChild(row);
        });

    } catch (error) {
        message.textContent = error.message;
    }
}

loadDashboard();
