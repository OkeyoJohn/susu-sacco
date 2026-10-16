
const button = document.getElementById("close-week-button");
const message = document.getElementById("message");
const weekNumber = document.getElementById("week-number");
const weeklyAmount = document.getElementById("weekly-amount");
const unpaidList = document.getElementById("unpaid-list");

const CONTRIBUTIONS_API = "../api/contributions.php";
const CLOSE_API = "../api/close-week.php";

function formatMoney(amount) {
    return "KSh " + Number(amount).toLocaleString("en-KE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

async function loadWeek() {
    try {
        const response = await fetch(CONTRIBUTIONS_API);
        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || "Could not load the current week.");
        }

        weekNumber.textContent = data.week_number;

        const amount = data.weekly_contribution_kes ??
            data.weekly_contribution_ghs ??
            (data.weekly_contribution_pesewas !== undefined
                ? Number(data.weekly_contribution_pesewas) / 100
                : null);

        weeklyAmount.textContent = amount === null
            ? "Unavailable"
            : formatMoney(amount);

        if (data.week_status !== "open") {
            button.disabled = true;
            message.textContent = "The current week is not open.";
        }
    } catch (error) {
        message.textContent = error.message;
    }
}

button.addEventListener("click", async () => {
    const confirmed = window.confirm(
        "Are you sure? This will close the current week and record the payout."
    );

    if (!confirmed) return;

    button.disabled = true;
    message.textContent = "Checking payments and processing payout...";
    unpaidList.replaceChildren();

    try {
        const response = await fetch(CLOSE_API, {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({})
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            if (Array.isArray(data.unpaid_members)) {
                data.unpaid_members.forEach((member) => {
                    const item = document.createElement("li");
                    item.textContent =
                        `${member.name} owes ${formatMoney(member.owed_kes)}`;
                    unpaidList.appendChild(item);
                });
            }

            throw new Error(data.message || "Could not close the week.");
        }

        message.textContent =
            `Week ${data.closed_week} closed successfully. ` +
            `${data.recipient} receives ${formatMoney(data.payout_kes)}. ` +
            `Week ${data.next_week} is now open.`;

        await loadWeek();
    } catch (error) {
        message.textContent = error.message;
        button.disabled = false;
    }
});

loadWeek();
