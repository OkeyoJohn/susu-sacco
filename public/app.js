
const form = document.getElementById("member-form");
const nameInput = document.getElementById("member-name");
const message = document.getElementById("message");
const memberList = document.getElementById("member-list");

const API_URL = "../api/members.php";

async function loadMembers() {
    try {
        const response = await fetch(API_URL);
        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || "Could not load members.");
        }

        memberList.replaceChildren();

        data.members.forEach((member) => {
            const item = document.createElement("li");

            // textContent prevents names being interpreted as HTML.
            item.textContent =
                `${member.name} (Member #${member.join_order})`;

            memberList.appendChild(item);
        });
    } catch (error) {
        message.textContent = error.message;
    }
}

form.addEventListener("submit", async (event) => {
    event.preventDefault();

    const name = nameInput.value.trim();

    if (!name) {
        message.textContent = "Please enter a member name.";
        return;
    }

    const button = form.querySelector("button");
    button.disabled = true;
    message.textContent = "Adding member...";

    try {
        const response = await fetch(API_URL, {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({ name })
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || "Could not add member.");
        }

        message.textContent = data.message;
        form.reset();

        await loadMembers();
    } catch (error) {
        message.textContent = error.message;
    } finally {
        button.disabled = false;
    }
});

// Load existing members when the page opens.
loadMembers();
